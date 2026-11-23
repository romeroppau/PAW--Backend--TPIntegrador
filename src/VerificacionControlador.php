<?php

// Verificación del WhatsApp (Entrega 5). Contrato: docs/api/contrato.md, sección "Verificación del
// WhatsApp". Reglas en VerificacionWhatsApp; SQL en VerificacionRepositorio.
// - Registro: POST /api/auth/registro/codigo manda el código; el registro (AuthControlador) crea la
//   cuenta solo si el código es correcto. Nunca queda una cuenta con el WhatsApp sin verificar.
// - Cambio de número: POST /api/cuenta/telefono/codigo manda el código al número nuevo y
//   POST /api/cuenta/telefono lo confirma. Recién ahí se cambia: mientras tanto sigue el anterior.
// En ENTORNO local y ci el WhatsApp no sale (WhatsAppCliente::simulado) y la respuesta trae el
// código, para poder probar el circuito.
class VerificacionControlador
{
    private const ZONA = 'America/Argentina/Buenos_Aires';

    // POST /api/auth/registro/codigo   { "telefono": "...", "nombre": "..." (opcional) }
    // Público: todavía no hay cuenta. El nombre solo se usa para el saludo del WhatsApp.
    public static function codigoDelRegistro(): void
    {
        $datos = Request::json();
        [$telefono, $error] = ValidadorRegistro::telefono($datos['telefono'] ?? null);
        if ($error !== null) {
            Response::error('Datos inválidos', 400, ['telefono' => $error]);
            return;
        }

        $nombre = is_string($datos['nombre'] ?? null) ? trim($datos['nombre']) : '';
        $nombre = $nombre === '' ? 'Hola' : mb_substr($nombre, 0, 100);

        self::mandarCodigo('REGISTRO', $telefono, null, $nombre);
    }

    // POST /api/cuenta/telefono/codigo   { "telefono": "..." }   (con sesión y CSRF)
    // Manda el código al número NUEVO. El teléfono de la cuenta todavía no cambia.
    public static function codigoDelCambio(): void
    {
        $sesion = Auth::requerirRol([]);
        Auth::verificarCsrf($sesion);

        [$telefono, $error] = ValidadorRegistro::telefono(Request::json()['telefono'] ?? null);
        if ($error !== null) {
            Response::error('Datos inválidos', 400, ['telefono' => $error]);
            return;
        }

        $cuenta = (new UsuarioRepositorio(Database::conectar()))->cuenta((int) $sesion['id']);
        if ($cuenta === null) {
            Response::error('No autenticado', 401);
            return;
        }
        if ($cuenta['telefono'] === $telefono) {
            Response::error('Datos inválidos', 400, ['telefono' => 'Ese ya es tu número']);
            return;
        }

        self::mandarCodigo('CAMBIO', $telefono, (int) $sesion['id'], $cuenta['nombre']);
    }

    // POST /api/cuenta/telefono   { "codigo": "123456" }   (con sesión y CSRF)
    // Si el código del número nuevo es correcto, cambia el teléfono y responde la cuenta.
    public static function confirmarCambio(): void
    {
        $sesion = Auth::requerirRol([]);
        Auth::verificarCsrf($sesion);
        $usuarioId = (int) $sesion['id'];

        $codigo = VerificacionWhatsApp::limpiarCodigo(Request::json()['codigo'] ?? null);
        if ($codigo === null) {
            Response::error('Datos inválidos', 400, ['codigo' => VerificacionWhatsApp::MENSAJE_FORMATO]);
            return;
        }

        $db = Database::conectar();
        $verificaciones = new VerificacionRepositorio($db);
        $usuarios = new UsuarioRepositorio($db);
        $ahora = self::ahora();

        $db->beginTransaction();
        try {
            $fila = $verificaciones->ultimoDelCambio($usuarioId, $ahora->getTimezone());
            $resultado = VerificacionWhatsApp::comprobar($fila, $codigo, $ahora);
            if ($resultado === VerificacionWhatsApp::INCORRECTO) {
                $verificaciones->sumarIntento($fila['id']);
            }
            if ($resultado !== VerificacionWhatsApp::OK) {
                $db->commit();   // guarda el intento sumado
                Response::error('Datos inválidos', 400, ['codigo' => VerificacionWhatsApp::MENSAJES[$resultado]]);
                return;
            }

            $verificaciones->marcarUsado($fila['id'], $ahora);
            $usuarios->cambiarTelefono($usuarioId, $fila['telefono']);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        Logger::info("Usuario $usuarioId: cambió su teléfono (verificado por WhatsApp)");
        Response::json($usuarios->cuenta($usuarioId), 200);
    }

    // Para el registro (AuthControlador), dentro de su transacción: comprueba el código del número y,
    // si es correcto, lo marca usado. Devuelve null si está bien, o el mensaje para "campos.codigo".
    public static function usarCodigoDelRegistro(PDO $db, string $telefono, mixed $codigo): ?string
    {
        $codigo = VerificacionWhatsApp::limpiarCodigo($codigo);
        if ($codigo === null) {
            return VerificacionWhatsApp::MENSAJE_FORMATO;
        }

        $verificaciones = new VerificacionRepositorio($db);
        $ahora = self::ahora();
        $fila = $verificaciones->ultimoDelRegistro($telefono, $ahora->getTimezone());
        $resultado = VerificacionWhatsApp::comprobar($fila, $codigo, $ahora);

        if ($resultado === VerificacionWhatsApp::INCORRECTO) {
            $verificaciones->sumarIntento($fila['id']);
        }
        if ($resultado !== VerificacionWhatsApp::OK) {
            return VerificacionWhatsApp::MENSAJES[$resultado];
        }

        $verificaciones->marcarUsado($fila['id'], $ahora);
        return null;
    }

    // Manda el código (si el número no pidió demasiados) y lo guarda. Responde 200, 400 (WhatsApp
    // no lo pudo entregar a ese número), 429 (tiene que esperar) o 503 (WhatsApp no anda).
    private static function mandarCodigo(string $proposito, string $telefono, ?int $usuarioId, string $nombre): void
    {
        $verificaciones = new VerificacionRepositorio(Database::conectar());
        $ahora = self::ahora();

        $espera = VerificacionWhatsApp::segundosDeEspera($verificaciones->enviosRecientes($telefono, $ahora), $ahora);
        if ($espera > 0) {
            Response::json([
                'error' => $espera <= VerificacionWhatsApp::SEGUNDOS_ENTRE_ENVIOS
                    ? "Esperá $espera segundos para pedir otro código"
                    : 'Pediste muchos códigos para este número. Probá de nuevo en ' . (int) ceil($espera / 60) . ' minutos.',
                'espera_segundos' => $espera,
            ], 429);
            return;
        }

        $codigo = VerificacionWhatsApp::generarCodigo();
        $resultado = (new WhatsAppCliente())->enviarCodigo($telefono, $nombre, $codigo);

        if (!$resultado->ok) {
            Logger::error("Verificación del WhatsApp ($proposito): no se pudo mandar el código ({$resultado->motivo})");
            if ($resultado->permanente) {
                Response::error('Datos inválidos', 400, [
                    'telefono' => 'No pudimos mandarte el código por WhatsApp a ese número. Revisá que sea tu celular y que tenga WhatsApp.',
                ]);
            } else {
                Response::error('No pudimos mandar el código por WhatsApp. Probá de nuevo en unos minutos.', 503);
            }
            return;
        }

        $verificaciones->guardar($proposito, $telefono, $usuarioId, $codigo, $ahora);

        $respuesta = [
            'mensaje' => "Te mandamos un código por WhatsApp al $telefono.",
            'telefono' => $telefono,
            'vence_en_minutos' => VerificacionWhatsApp::MINUTOS_DE_VIGENCIA,
            'reenviar_en_segundos' => VerificacionWhatsApp::SEGUNDOS_ENTRE_ENVIOS,
        ];
        // Solo en local y en el CI, donde el WhatsApp no sale de verdad: para poder probar.
        if (WhatsAppCliente::simulado()) {
            $respuesta['codigo'] = $codigo;
        }
        Response::json($respuesta, 200);
    }

    private static function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::ZONA));
    }
}
