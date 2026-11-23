<?php

// "Mis datos" (Entrega 5): cada usuario (cliente, rescatista o veterinaria) ve los datos de su cuenta,
// cambia su nombre y su teléfono (el rescatista también su organización) y cambia su contraseña.
// El email no se cambia: es el usuario para entrar. El usuario sale siempre del JWT: nadie puede
// cambiar los datos de otro. Contrato: docs/api/contrato.md, sección "Mis datos".
class CuentaControlador
{
    // GET /api/cuenta
    public static function ver(): void
    {
        $sesion = Auth::requerirRol([]);
        $cuenta = self::repositorio()->cuenta((int) $sesion['id']);

        if ($cuenta === null) {
            Response::error('No autenticado', 401);
            return;
        }

        Response::json($cuenta, 200);
    }

    // PUT /api/cuenta   { "nombre": "...", "telefono": "...", "organizacion": "..." }
    public static function guardar(): void
    {
        $sesion = Auth::requerirRol([]);
        Auth::verificarCsrf($sesion);

        [$limpios, $errores] = ValidadorRegistro::cuenta(Request::json(), $sesion['rol']);
        if ($errores !== []) {
            Response::error('Datos inválidos', 400, $errores);
            return;
        }

        $usuarios = self::repositorio();
        $esRescatista = $sesion['rol'] === 'RESCATISTA';
        $usuarios->actualizarDatos(
            (int) $sesion['id'],
            $limpios['nombre'],
            $limpios['telefono'],
            $esRescatista ? $limpios['organizacion'] : null,
            $esRescatista
        );

        Response::json($usuarios->cuenta((int) $sesion['id']), 200);
    }

    // PUT /api/cuenta/contrasena   { "actual": "...", "nueva": "...", "repetir": "..." }
    // La sesión sigue abierta: la contraseña nueva se usa la próxima vez que se inicia sesión.
    public static function cambiarContrasena(): void
    {
        $sesion = Auth::requerirRol([]);
        Auth::verificarCsrf($sesion);

        [$limpios, $errores] = ValidadorRegistro::cambioDeContrasena(Request::json());
        if ($errores !== []) {
            Response::error('Datos inválidos', 400, $errores);
            return;
        }

        $usuarios = self::repositorio();
        $usuario = $usuarios->buscarPorId((int) $sesion['id']);

        // La actual tiene que ser la guardada: así, con una sesión abierta en otra compu, nadie la cambia
        // sin saberla. Es un 400 en el campo (no un 401: la sesión es válida y no se cierra).
        if ($usuario === null || !$usuario->iniciarSesion($limpios['actual'])) {
            Response::error('Datos inválidos', 400, ['actual' => 'La contraseña actual no es correcta']);
            return;
        }

        $usuarios->actualizarPassword((int) $sesion['id'], AuthControlador::hashear($limpios['nueva']));
        Response::json(['mensaje' => 'Tu contraseña se cambió'], 200);
    }

    private static function repositorio(): UsuarioRepositorio
    {
        return new UsuarioRepositorio(Database::conectar());
    }
}
