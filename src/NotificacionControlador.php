<?php

class NotificacionControlador
{
    private const TIPOS = ['VACUNA', 'CONTROL_ADOPCION', 'STOCK_BAJO', 'PROMOCION', 'CUENTA', 'POSTULACION', 'COMPRA', 'TURNO'];
    // Avisos que nacen de algo que hizo una persona (la veterinaria da un turno, la rescatista elige,
    // se paga una compra): el WhatsApp sale en el momento, sin esperar a la tarea programada (25/11).
    // Los del motor diario (vacunas, controles, stock, promociones) siguen saliendo con la tarea.
    private const ENVIO_INMEDIATO = ['TURNO', 'POSTULACION', 'COMPRA', 'CUENTA'];
    // Tiempo máximo del envío inmediato: quien llama espera hasta 3 segundos (si no alcanza, el aviso
    // queda pendiente y lo manda la tarea).
    private const TIMEOUT_INMEDIATO = 2;
    private const CANALES = ['WEB', 'WHATSAPP'];
    private const MAX_MENSAJE = 500;
    private const MAX_ID_OPERACION = 100;

    // POST /internal/notificaciones (solo entre módulos)
    public static function crearInterno(): void
    {
        // 1. Token de servicio, antes de leer el cuerpo.
        $esperado = Config::get('INTERNAL_TOKEN');
        $recibido = $_SERVER['HTTP_X_SERVICIO_TOKEN'] ?? '';

        if (
            $esperado === null || $esperado === '' || $recibido === '' ||
            !hash_equals($esperado, $recibido)
        ) {
            Logger::info('Llamada interna rechazada: /internal/notificaciones sin token válido');
            Response::error('No autorizado', 401);
            return;
        }

        // 2. Validar el cuerpo (tipos estrictos: "15" o 15.5 se rechazan).
        $d = Request::json();

        $usuarioId = $d['usuario_id'] ?? null;
        $tipo = $d['tipo'] ?? null;
        $canal = $d['canal'] ?? null;
        $mensaje = $d['mensaje'] ?? null;
        $fecha = $d['fecha_programada'] ?? null;
        $idOperacion = $d['id_operacion'] ?? null;

        $valido =
            is_int($usuarioId) && $usuarioId > 0
            && in_array($tipo, self::TIPOS, true)
            && in_array($canal, self::CANALES, true)
            && is_string($mensaje) && trim($mensaje) !== '' && mb_strlen($mensaje) <= self::MAX_MENSAJE
            && is_string($fecha) && self::fechaValida($fecha)
            && is_string($idOperacion) && trim($idOperacion) !== ''
            && strlen($idOperacion) <= self::MAX_ID_OPERACION;

        if (!$valido) {
            Response::error('Datos de solicitud inválidos', 400);
            return;
        }

        // 3. Guardar. Repetido: 200 aplicado:false.
        $repo = new NotificacionRepositorio(Database::conectar());

        if (!$repo->existeUsuario($usuarioId)) {
            Response::error('Usuario no encontrado', 404);
            return;
        }

        $aplicado = $repo->insertar(
            $usuarioId, $tipo, $canal, trim($mensaje), $fecha, trim($idOperacion)
        );

        // WhatsApp de algo que acaba de pasar: sale ya (si falla, queda para la tarea).
        if ($aplicado && $canal === 'WHATSAPP' && in_array($tipo, self::ENVIO_INMEDIATO, true) && $fecha <= date('Y-m-d H:i:s')) {
            $aviso = $repo->pendienteWhatsAppPorOperacion(trim($idOperacion));
            if ($aviso !== null) {
                self::enviarUno($repo, new WhatsAppCliente(self::TIMEOUT_INMEDIATO), $aviso);
            }
        }

        Response::json(['aplicado' => $aplicado], 200);
    }

    // GET /api/notificaciones   (la campana: las 10 sin leer más nuevas y cuántas hay sin leer)
    public static function listar(): void
    {
        $sesion = Auth::requerirRol([]);   // 401 si no hay sesión
        $repo = new NotificacionRepositorio(Database::conectar());

        Response::json([
            'no_leidas' => $repo->contarNoLeidas((int) $sesion['id']),
            'datos' => $repo->listarDeUsuario((int) $sesion['id']),
        ], 200);
    }

    // GET /api/notificaciones/historial?estado=SIN_LEER&desde=2026-10-25&hasta=2026-11-23&pagina=1
    // "Mis notificaciones": todos los avisos web del usuario, con filtros, de a 20. Sin fechas: los
    // últimos 30 días (hoy incluido).
    public static function historial(): void
    {
        $sesion = Auth::requerirRol([]);
        $errores = [];

        $estado = $_GET['estado'] ?? 'TODAS';
        if (!is_string($estado) || !in_array($estado, ['TODAS', 'SIN_LEER', 'LEIDAS'], true)) {
            $errores['estado'] = 'Tiene que ser TODAS, SIN_LEER o LEIDAS';
        }

        $pagina = $_GET['pagina'] ?? '1';
        if (!is_string($pagina) || !preg_match('/^[1-9]\d{0,5}$/', $pagina)) {
            $errores['pagina'] = 'Tiene que ser un número entero desde 1';
        }

        $hoy = new DateTimeImmutable('today');
        $hasta = self::fechaDelFiltro($_GET['hasta'] ?? null, $hoy, 'hasta', $errores);
        $desde = self::fechaDelFiltro($_GET['desde'] ?? null, ($hasta ?? $hoy)->modify('-29 days'), 'desde', $errores);
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $errores['desde'] = 'Tiene que ser antes o igual que "hasta"';
        }

        if ($errores !== []) {
            Response::error('Datos inválidos', 400, $errores);
            return;
        }

        $repo = new NotificacionRepositorio(Database::conectar());
        [$avisos, $hayMas] = $repo->historial(
            (int) $sesion['id'],
            $estado === 'TODAS' ? null : $estado,
            $desde->format('Y-m-d'),
            $hasta->format('Y-m-d'),
            (int) $pagina,
            self::POR_PAGINA
        );

        Response::json([
            'datos' => $avisos,
            'pagina' => (int) $pagina,
            'por_pagina' => self::POR_PAGINA,
            'hay_mas' => $hayMas,
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'no_leidas' => $repo->contarNoLeidas((int) $sesion['id']),
        ], 200);
    }

    // POST /api/notificaciones/leidas   (marcar todas como leídas)
    public static function marcarTodasLeidas(): void
    {
        $sesion = Auth::requerirRol([]);
        Auth::verificarCsrf($sesion);

        $marcadas = (new NotificacionRepositorio(Database::conectar()))->marcarTodasLeidas((int) $sesion['id']);
        Response::json(['marcadas' => $marcadas, 'no_leidas' => 0], 200);
    }

    // POST /api/notificaciones/{id}/leida
    public static function marcarLeida(array $params = []): void
    {
        $sesion = Auth::requerirRol([]);
        Auth::verificarCsrf($sesion);      // 403 si falta el token

        $id = $params['id'] ?? null;
        if (!is_string($id) && !is_int($id)) { $id = null; }

        if ($id === null || !ctype_digit((string) $id) || (int) $id <= 0) {
            Response::error('Notificación no encontrada', 404);
            return;
        }

        $repo = new NotificacionRepositorio(Database::conectar());

        // De otro usuario o inexistente: mismo 404, para no revelar que existe.
        if (!$repo->marcarLeida((int) $id, (int) $sesion['id'])) {
            Response::error('Notificación no encontrada', 404);
            return;
        }

        Response::json(['id' => (int) $id, 'leida' => true], 200);
    }

    private const POR_PAGINA = 20;

    // Una fecha "AAAA-MM-DD" del filtro; si no vino (o vino vacía), $defecto. Si es inválida, anota el error.
    private static function fechaDelFiltro(mixed $valor, DateTimeImmutable $defecto, string $campo, array &$errores): ?DateTimeImmutable
    {
        if ($valor === null || $valor === '') {
            return $defecto;
        }

        $fecha = is_string($valor) ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            $errores[$campo] = 'Tiene que ser una fecha AAAA-MM-DD';
            return null;
        }

        return $fecha;
    }

    private static function fechaValida(string $f): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $f);
        return $dt !== false && $dt->format('Y-m-d H:i:s') === $f;
    }

    // POST /api/notificaciones/tareas/enviar (lo llama GitHub Actions cada 15 minutos). Los avisos de
    // ENVIO_INMEDIATO ya salieron al crearse; acá salen los del motor y los que fallaron.
    public static function enviarPendientes(): void
    {
        $esperado = Config::get('TAREAS_TOKEN');
        $recibido = $_SERVER['HTTP_X_TAREA_TOKEN'] ?? '';

        if (
            $esperado === null || $esperado === '' || $recibido === '' ||
            !hash_equals($esperado, $recibido)
        ) {
            Logger::info('Tarea de envío rechazada: token inválido');
            Response::error('No autorizado', 401);
            return;
        }

        $repo = new NotificacionRepositorio(Database::conectar());
        $cliente = new WhatsAppCliente();
        $enviadas = 0;
        $reintentar = 0;    // fallaron por algo pasajero: la próxima corrida los vuelve a probar
        $descartadas = 0;   // no van a salir nunca por WhatsApp (quedan en la web)
        $configuracion = null;

        foreach ($repo->pendientesWhatsApp(20) as $aviso) {
            $resultado = self::enviarUno($repo, $cliente, $aviso);

            if ($resultado === 'ENVIADA') {
                $enviadas++;
            } elseif ($resultado === 'DESCARTADA') {
                $descartadas++;
            } elseif ($resultado === 'REINTENTAR') {
                $reintentar++;
            } elseif (str_starts_with($resultado, 'CONFIGURACION:')) {
                // Token vencido, falta una variable, plantilla mal: fallarían todos. Se corta acá;
                // salen solos cuando se arregla la configuración.
                $configuracion = substr($resultado, strlen('CONFIGURACION:'));
                break;
            }
        }

        $respuesta = [
            'enviadas' => $enviadas,
            'fallidas' => $reintentar + $descartadas,   // se mantiene por compatibilidad
            'reintentar' => $reintentar,
            'descartadas' => $descartadas,
            'simulado' => WhatsAppCliente::simulado(),
        ];
        if ($configuracion !== null) {
            $respuesta['error_configuracion'] = $configuracion;
        }

        // 200 igual: la tarea corrió. El workflow muestra el resumen en su log.
        Response::json($respuesta, 200);
    }

    // Manda un aviso de WhatsApp (de la tarea o el envío inmediato). Devuelve ENVIADA, DESCARTADA (no va a
    // salir nunca: queda en la web), REINTENTAR (algo pasajero), CONFIGURACION:<motivo> u OCUPADA (otra
    // corrida lo tomó primero: no se manda dos veces).
    private static function enviarUno(NotificacionRepositorio $repo, WhatsAppCliente $cliente, array $aviso): string
    {
        $id = (int) $aviso['id'];

        if (!$repo->tomarParaEnviar($id, (int) $aviso['intentos'])) {
            return 'OCUPADA';
        }

        $telefono = trim((string) ($aviso['telefono'] ?? ''));
        if ($telefono === '') {
            $repo->registrarFallo($id, 'SIN_TELEFONO', true);
            return 'DESCARTADA';
        }

        $r = $cliente->enviar($telefono, self::limpiar((string) $aviso['nombre']), self::limpiar((string) $aviso['mensaje']));

        if ($r->ok) {
            $repo->marcarEnviada($id);
            return 'ENVIADA';
        }
        if ($r->deConfiguracion) {
            $repo->devolverIntento($id, $r->motivo);
            return 'CONFIGURACION:' . $r->motivo;
        }
        $repo->registrarFallo($id, $r->motivo, $r->permanente);

        return $r->permanente ? 'DESCARTADA' : 'REINTENTAR';
    }

    // Meta rechaza saltos de línea y más de 4 espacios seguidos en los parámetros de plantilla.
    private static function limpiar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', $texto));
    }
}