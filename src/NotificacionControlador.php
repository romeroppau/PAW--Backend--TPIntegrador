<?php

class NotificacionControlador
{
    private const TIPOS = ['VACUNA', 'CONTROL_ADOPCION', 'STOCK_BAJO', 'PROMOCION', 'CUENTA', 'POSTULACION'];
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

        Response::json(['aplicado' => $aplicado], 200);
    }

    // GET /api/notificaciones
    public static function listar(): void
    {
        $sesion = Auth::requerirRol([]);   // 401 si no hay sesión
        $repo = new NotificacionRepositorio(Database::conectar());

        Response::json([
            'no_leidas' => $repo->contarNoLeidas((int) $sesion['id']),
            'datos' => $repo->listarDeUsuario((int) $sesion['id']),
        ], 200);
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

    private static function fechaValida(string $f): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $f);
        return $dt !== false && $dt->format('Y-m-d H:i:s') === $f;
    }
}