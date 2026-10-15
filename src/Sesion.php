<?php

// Arma y borra la sesión: genera el código CSRF, crea el JWT y pone la cookie huellavet_sesion.
// La usan el login y los dos registros (que dejan la sesión iniciada), /yo y el logout.
// Las reglas de la cookie están en SEGURIDAD.md y en el contrato de la API (docs/api/contrato.md).
class Sesion
{
    public const COOKIE = 'huellavet_sesion';

    // Inicia la sesión de un usuario y devuelve los datos que van en el JSON de la respuesta.
    public static function iniciar(Cliente|Veterinario|Rescatista $usuario): array
    {
        // Código CSRF: 32 bytes aleatorios pasados a texto (64 caracteres).
        // random_bytes es el generador seguro de PHP; nunca usar rand() para esto.
        // Va adentro del JWT y también en la respuesta, para que el frontend lo mande en el header X-CSRF-Token.
        $csrf = bin2hex(random_bytes(32));

        // La clave privada sale de la variable de entorno (el .env en local, el panel de Render en producción).
        $clavePrivada = Config::get('JWT_PRIVATE_KEY');

        if ($clavePrivada === null || $clavePrivada === '') {
            throw new RuntimeException('No está configurada la clave privada del JWT');
        }

        $token = Jwt::crear($usuario->getId(), $usuario->getRol(), $csrf, $clavePrivada);

        // La cookie dura lo mismo que el JWT (12 horas).
        // httponly: el JavaScript de la página no la puede leer (protege contra XSS).
        // secure: solo viaja por HTTPS.
        // samesite Strict: el navegador no la manda si el pedido viene de otro sitio (protege contra CSRF).
        // path '/': se manda en todos los pedidos al sitio.
        setcookie(self::COOKIE, $token, [
            'expires' => time() + Jwt::DURACION,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        return self::datos($usuario, $csrf);
    }

    // Borra la cookie: se pone vacía y con una fecha de vencimiento en el pasado, y el navegador la elimina.
    // El JWT no se puede anular en el servidor: un token copiado sigue sirviendo hasta que vence (SEGURIDAD.md).
    public static function cerrar(): void
    {
        setcookie(self::COOKIE, '', [
            'expires' => 1,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    // El objeto de sesión que devuelven el login, los registros y /yo (siempre el mismo, según el contrato).
    // Nunca incluye el hash de la contraseña.
    public static function datos(Cliente|Veterinario|Rescatista $usuario, string $csrf): array
    {
        return [
            'id' => $usuario->getId(),
            'nombre' => $usuario->getNombre(),
            'email' => $usuario->getEmail(),
            'rol' => $usuario->getRol(),
            'csrf' => $csrf
        ];
    }
}
