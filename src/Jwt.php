<?php

// Crea el JWT de la sesión. Solo Backend firma tokens, porque es el único que tiene la clave privada.
// Los demás módulos los verifican con la clave pública, usando Auth::usuarioActual() de comun/auth.php.
// Por eso el formato tiene que coincidir exactamente con lo que espera Auth:
// header {"alg":"RS256","typ":"JWT"}, payload {id, rol, csrf, exp} y todo en base64url.
class Jwt
{
    // 12 horas, en segundos. Es la misma duración que tiene la cookie de sesión.
    public const DURACION = 43200;

    public static function crear(
        int $id,
        string $rol,
        string $csrf,
        string $clavePrivada
    ): string { //el token
        $cabecera = [
            'alg' => 'RS256', // firma RSA con SHA-256: Auth rechaza cualquier otro algoritmo
            'typ' => 'JWT'
        ];

        $datos = [
            'id' => $id,
            'rol' => $rol,
            'csrf' => $csrf,
            'exp' => time() + self::DURACION // momento en que vence, en segundos desde 1970
        ];

        // Las dos primeras partes del token: el JSON pasado a base64url.
        $contenido = self::codificarBase64Url(json_encode($cabecera)) 
            . '.'
            . self::codificarBase64Url(json_encode($datos));

        // En la variable de entorno, la clave viene en una sola línea con "\n" escrito como texto.
        // Se cambian por saltos de línea reales, igual que hace Auth con la clave pública.
        $clave = openssl_pkey_get_private(str_replace('\\n', "\n", $clavePrivada));

        if ($clave === false) {
            throw new RuntimeException('No se pudo cargar la clave privada del JWT');
        }

        // La firma se calcula sobre "cabecera.datos". Si alguien cambia un solo carácter,
        // la firma deja de coincidir y Auth rechaza el token.
        if (!openssl_sign($contenido, $firma, $clave, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el JWT');
        }

        return $contenido . '.' . self::codificarBase64Url($firma);
    }

    // base64url: como base64, pero cambia + y / por - y _, y saca los = del final,
    // así el token se puede guardar en una cookie sin problemas.
    private static function codificarBase64Url(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
