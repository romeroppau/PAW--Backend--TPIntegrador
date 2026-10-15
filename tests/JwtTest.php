<?php

use PHPUnit\Framework\TestCase;

// Pruebas del JWT: el token lo crea Jwt::crear (Backend)
// y lo verifica Auth::usuarioActual() (comun/), que es lo que usan todos los módulos.
// Probamos: Si Jwt::crear arma un token y se lo damos a Auth, Auth lo acepta y lee bien el id, el rol y el CSRF.
final class JwtTest extends TestCase
{
    private static string $clavePrivada;
    private static string $clavePublica;

    // PHPUnit la ejecuta una sola vez, antes de las pruebas de esta clase.
    // Genera el par de claves "de Backend" que usan las pruebas.
    public static function setUpBeforeClass(): void
    {
        [self::$clavePrivada, self::$clavePublica] = self::generarParDeClaves();
    }


    public function testAuthAceptaUnTokenCreadoPorJwt(): void
    {
        // 1. Preparar: Auth lee la clave pública de la variable de entorno, como en producción.
        putenv('JWT_PUBLIC_KEY=' . self::$clavePublica);

        // 2. Ejecutar: crear el token y ponerlo en la cookie, como haría el navegador.
        $token = Jwt::crear(15, 'CLIENTE', 'codigo-csrf', self::$clavePrivada);
        $_COOKIE['huellavet_sesion'] = $token;

        $usuario = Auth::usuarioActual();

        // 3. Comprobar: Auth aceptó el token y leyó bien los datos.
        $this->assertNotNull($usuario);
        $this->assertSame(15, $usuario['id']);
        $this->assertSame('CLIENTE', $usuario['rol']);
        $this->assertSame('codigo-csrf', $usuario['csrf']);
    }

    // Ahora probamos lo que no tiene que pasar. Hay que confirmar que Auth rechaza los tokens truchos, porque si los aceptara, cualquiera podría hacerse pasar por otro.
    // El ataque: Un cliente inicia sesión y recibe su token. Como los datos se pueden leer (lo viste en la prueba a mano), se le ocurre:
    // separar las tres partes;
    // decodificar los datos y cambiar "rol":"CLIENTE" por "rol":"VETERINARIO";
    // volver a armar el token con la firma original, porque no tiene la clave privada para hacer una nueva;
    // ponerlo en su cookie.
    // Si Auth aceptara ese token, el cliente podría dar de alta productos como si fuera el veterinario. Lo esperado: como los datos cambiaron, la firma ya no coincide, y Auth devuelve null.
    public function testRechazaUnTokenAlQueLeCambiaronElRol(): void{
        // 1. Preparar: un token válido de un cliente.
        putenv('JWT_PUBLIC_KEY=' . self::$clavePublica);
        $token = Jwt::crear(15, 'CLIENTE', 'codigo-csrf', self::$clavePrivada);

        // 2. Ejecutar el ataque: separar las tres partes, cambiar el rol en los datos
        //    y volver a armar el token con la firma original.
        [$cabecera, $datos, $firma] = explode('.', $token);

        $contenido = json_decode(base64_decode(strtr($datos, '-_', '+/')), true);
        $contenido['rol'] = 'VETERINARIO';
        $datosFalsos = rtrim(strtr(base64_encode(json_encode($contenido)), '+/', '-_'), '=');

        $_COOKIE['huellavet_sesion'] = "$cabecera.$datosFalsos.$firma";

        // 3. Comprobar: Auth rechaza el token.
        $this->assertNull(Auth::usuarioActual());
    }


    // Genera un par de claves RSA nuevo y devuelve [privada, pública] en formato texto (como los .pem).
    private static function generarParDeClaves(): array
    {
        $par = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ]);

        openssl_pkey_export($par, $privada);

        return [$privada, openssl_pkey_get_details($par)['key']];
    }

    // El atacante genera su propio par de claves y fabrica un token de veterinario bien armado,
    // firmado con SU clave privada. Auth verifica con la clave pública de Backend, así que lo tiene que rechazar.
    public function testRechazaUnTokenFirmadoConOtraClave(): void
    {
        // 1. Preparar: Auth tiene la clave pública de Backend. El atacante tiene otra clave privada.
        putenv('JWT_PUBLIC_KEY=' . self::$clavePublica);
        [$clavePrivadaDelAtacante] = self::generarParDeClaves();

        // 2. Ejecutar el ataque: el token lo crea la misma función Jwt::crear, pero con la clave del atacante.
        $_COOKIE['huellavet_sesion'] = Jwt::crear(15, 'VETERINARIO', 'codigo-csrf', $clavePrivadaDelAtacante);

        // 3. Comprobar: Auth rechaza el token.
        $this->assertNull(Auth::usuarioActual());
    }

    //Prueba: token vencido --> Auth tiene que rechazarlo.
    // Un token con firma correcta pero vencido. Lo armamos a mano, con los mismos pasos que Jwt::crear,
    // porque Jwt::crear siempre lo hace vencer dentro de 12 horas.
    public function testRechazaUnTokenVencido(): void
    {
        // 1. Preparar: los datos de un cliente, pero con un vencimiento de hace 10 segundos.
        putenv('JWT_PUBLIC_KEY=' . self::$clavePublica);

        $cabecera = ['alg' => 'RS256', 'typ' => 'JWT'];
        $datos = ['id' => 15, 'rol' => 'CLIENTE', 'csrf' => 'codigo-csrf', 'exp' => time() - 10];

        // 2. Ejecutar: armar y firmar el token con la clave CORRECTA de Backend.
        $contenido = rtrim(strtr(base64_encode(json_encode($cabecera)), '+/', '-_'), '=')
            . '.'
            . rtrim(strtr(base64_encode(json_encode($datos)), '+/', '-_'), '=');

        openssl_sign($contenido, $firma, self::$clavePrivada, OPENSSL_ALGO_SHA256);

        $_COOKIE['huellavet_sesion'] = $contenido . '.' . rtrim(strtr(base64_encode($firma), '+/', '-_'), '=');

        // 3. Comprobar: aunque la firma es válida, Auth lo rechaza porque venció.
        $this->assertNull(Auth::usuarioActual());
    }

    //El estándar de JWT permite que la cabecera diga "alg":"none", que significa "este token no tiene firma"
    // el Auth solo debe aceptar RS256 por lo que debe rechazarlo
    public function testRechazaUnTokenSinFirmaConAlgNone(): void
    {
        // 1. Preparar: una cabecera que dice "sin firma" y los datos de un veterinario.
        putenv('JWT_PUBLIC_KEY=' . self::$clavePublica);

        $cabecera = ['alg' => 'none', 'typ' => 'JWT'];
        $datos = ['id' => 15, 'rol' => 'VETERINARIO', 'csrf' => 'codigo-csrf', 'exp' => time() + 3600];

        // 2. Ejecutar el ataque: cabecera y datos en base64url, y la firma vacía (el token termina en punto).
        $_COOKIE['huellavet_sesion'] = rtrim(strtr(base64_encode(json_encode($cabecera)), '+/', '-_'), '=')
            . '.'
            . rtrim(strtr(base64_encode(json_encode($datos)), '+/', '-_'), '=')
            . '.';

        // 3. Comprobar: Auth lo rechaza.
        $this->assertNull(Auth::usuarioActual());
    }

    // Si en la cookie hay cualquier cosa que no es un token, Auth no se rompe: devuelve null.
    public function testRechazaTextoQueNoEsUnToken(): void
    {
        putenv('JWT_PUBLIC_KEY=' . self::$clavePublica);

        $_COOKIE['huellavet_sesion'] = 'esto-no-es-un-token'; //si no tiene puntos es porque no tiene tres partes->no es un cookie
        $this->assertNull(Auth::usuarioActual());

        $_COOKIE['huellavet_sesion'] = 'a.b.c'; //tiene tres partes pero no son base64url valido ni JSON
        $this->assertNull(Auth::usuarioActual());

        $_COOKIE['huellavet_sesion'] = ''; // la cookie existe pero esta vacia
        $this->assertNull(Auth::usuarioActual());
    }

    // Sin cookie (alguien que nunca inició sesión), Auth devuelve null.
    public function testSinCookieNoHaySesion(): void
    {
        unset($_COOKIE['huellavet_sesion']);

        $this->assertNull(Auth::usuarioActual());
    }

    //Prueba si jwt::crear y Auth pudieron sacar los saltos de lineas y convertirlos en \n
    public function testFuncionaConLasClavesEnUnaSolaLineaComoEnElEnv(): void
    {
        // 1. Preparar: pasar las dos claves al formato del .env (cada salto de línea real se cambia por el texto \n).
        $privadaEnUnaLinea = str_replace("\n", '\n', self::$clavePrivada);
        $publicaEnUnaLinea = str_replace("\n", '\n', self::$clavePublica);

        putenv('JWT_PUBLIC_KEY=' . $publicaEnUnaLinea);

        // 2. Ejecutar: crear el token con la privada en una línea.
        $_COOKIE['huellavet_sesion'] = Jwt::crear(7, 'VETERINARIO', 'codigo-csrf', $privadaEnUnaLinea);

        $usuario = Auth::usuarioActual();

        // 3. Comprobar: Auth acepta el token y lee bien los datos.
        $this->assertNotNull($usuario);
        $this->assertSame(7, $usuario['id']);
        $this->assertSame('VETERINARIO', $usuario['rol']);
    }

        // Si la clave privada no es válida (por ejemplo, no se cargó bien en el .env), Jwt::crear no puede firmar.
    // En ese caso tiene que avisar con un error, en vez de devolver un token roto.
    public function testCrearConUnaClaveInvalidaDaError(): void
    {
        // Comprobar: lo que viene a continuación tiene que lanzar un RuntimeException.
        $this->expectException(RuntimeException::class);

        // Ejecutar: crear un token con un texto que no es una clave.
        Jwt::crear(15, 'CLIENTE', 'codigo-csrf', 'esto no es una clave');
    }




}

