<?php

use PHPUnit\Framework\TestCase;

// Pruebas del UsuarioRepositorio contra una base MySQL de PRUEBA (nunca contra Aiven):
// el MySQL local del Docker Compose de ApiGateway (perfil "local"), base huellavet_test, puerto 3307.
// Para correrlas:
//   1. En ApiGateway: docker compose --profile local up -d db
//   2. export TEST_DB_PASSWORD="$(grep '^MYSQL_ROOT_PASSWORD=' .env | cut -d= -f2-)"
// Si la base no está disponible, estas pruebas se saltean y las demás siguen funcionando.
final class UsuarioRepositorioTest extends TestCase
{
    private static ?PDO $db = null;
    private UsuarioRepositorio $usuarios;

    // Una sola vez, antes de todas las pruebas: se conecta a la base de prueba
    // y crea la tabla usuario con la migración real (base/001_backend_usuario.sql de ApiGateway).
    public static function setUpBeforeClass(): void
    {
        $password = getenv('TEST_DB_PASSWORD');

        if ($password === false || $password === '') {
            return; // sin contraseña no hay base: setUp() saltea las pruebas
        }

        try {
            self::$db = new PDO(
                'mysql:host=127.0.0.1;port=3307;dbname=huellavet_test;charset=utf8mb4',
                'root',
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $error) {
            self::$db = null; // la base no está levantada: setUp() saltea las pruebas
            return;
        }

        // La migración está en ApiGateway: base/ queda al lado de comun/.
        $migracion = (getenv('COMUN_DIR') ?: __DIR__ . '/../../comun') . '/../base/001_backend_usuario.sql';

        self::$db->exec('DROP TABLE IF EXISTS usuario');
        self::$db->exec(file_get_contents($migracion));
    }

    // Antes de CADA prueba: vacía la tabla, así cada prueba arranca limpia y no depende de las otras.
    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('No hay base de prueba: levantá el MySQL local y cargá TEST_DB_PASSWORD');
        }

        self::$db->exec('DELETE FROM usuario');
        $this->usuarios = new UsuarioRepositorio(self::$db);
    }

    // Grupo 1: registro.
    public function testInsertarUnClienteYBuscarloPorEmail(): void
    {
        $id = $this->usuarios->insertarCliente('Ana Pérez', 'ana@mail.com', self::hash('clave12345'), '2323555555');

        $usuario = $this->usuarios->buscarPorEmail('ana@mail.com');

        // assertInstanceOf: comprueba que el objeto sea de esa clase (el repositorio eligió bien según el rol).
        $this->assertInstanceOf(Cliente::class, $usuario);
        $this->assertSame($id, $usuario->getId());
        $this->assertSame('Ana Pérez', $usuario->getNombre());
        $this->assertSame('2323555555', $usuario->getTelefono());
        $this->assertSame('CLIENTE', $usuario->getRol());
    }

    public function testBuscarUnEmailQueNoExisteDevuelveNull(): void
    {
        $this->assertNull($this->usuarios->buscarPorEmail('nadie@mail.com'));
    }

    public function testUnRescatistaSeCreaPendienteYNoPuedePublicar(): void
    {
        $id = $this->usuarios->insertarRescatista('Juan Gómez', 'juan@mail.com', self::hash('clave12345'), '2323444444', null);

        $usuario = $this->usuarios->buscarPorId($id);

        $this->assertInstanceOf(Rescatista::class, $usuario);
        $this->assertSame('PENDIENTE', $usuario->getEstadoValidacion());
        $this->assertNull($usuario->getOrganizacion());
        $this->assertFalse($usuario->puedePublicar()); // assertFalse: comprueba que sea false
    }

        // Grupo 2: la base no deja dos cuentas con el mismo email (el UNIQUE de la migración).
    // El registro atrapa este error (código 23000) y responde 409 "Ya existe una cuenta con ese email".
    public function testNoSePuedenRegistrarDosCuentasConElMismoEmail(): void
    {
        $this->usuarios->insertarCliente('Ana Pérez', 'ana@mail.com', self::hash('clave12345'), '2323555555');

        try {
            $this->usuarios->insertarRescatista('Otra Ana', 'ana@mail.com', self::hash('otraClave1'), '2323444444', null);
            $this->fail('Tendría que haber rechazado el email repetido'); // fail: marca la prueba como fallida
        } catch (PDOException $error) {
            $this->assertSame('23000', (string) $error->getCode());
        }
    }

    // Grupo 3: la contraseña se guarda hasheada (nunca en limpio) y iniciarSesion la verifica.
    public function testLaContrasenaSeGuardaHasheadaYSeVerifica(): void
    {
        $this->usuarios->insertarCliente('Ana Pérez', 'ana@mail.com', self::hash('clave12345'), '2323555555');

        $guardada = self::$db->query("SELECT password_hash FROM usuario")->fetchColumn();
        $this->assertNotSame('clave12345', $guardada);
        $this->assertStringStartsWith('$2y$', $guardada); // así empiezan los hashes de bcrypt

        $usuario = $this->usuarios->buscarPorEmail('ana@mail.com');
        $this->assertTrue($usuario->iniciarSesion('clave12345'));
        $this->assertFalse($usuario->iniciarSesion('otraClave'));
    }

    // Grupo 4: bloqueo por intentos. 4 fallos no bloquean, el 5° sí, y un login correcto desbloquea.
    public function testCincoFallosBloqueanYUnLoginCorrectoDesbloquea(): void
    {
        $id = $this->usuarios->insertarCliente('Ana Pérez', 'ana@mail.com', self::hash('clave12345'), '2323555555');

        for ($i = 1; $i <= 4; $i++) {
            $this->usuarios->registrarFalloDeLogin($id);
        }
        $this->assertFalse($this->usuarios->estaBloqueado($id), 'Con 4 fallos todavía no se bloquea');

        $this->usuarios->registrarFalloDeLogin($id);
        $this->assertTrue($this->usuarios->estaBloqueado($id), 'Con el 5° fallo se bloquea');

        $this->usuarios->reiniciarFallos($id);
        $this->assertFalse($this->usuarios->estaBloqueado($id), 'Un login correcto desbloquea');
    }

    // Los 5 fallos tienen que ser dentro de 15 minutos desde el primero. Si el primero fue hace más,
    // la cuenta empieza una serie nueva de cero.
    public function testSiElPrimerFalloFueHaceMasDe15MinutosEmpiezaDeCero(): void
    {
        $id = $this->usuarios->insertarCliente('Ana Pérez', 'ana@mail.com', self::hash('clave12345'), '2323555555');

        for ($i = 1; $i <= 4; $i++) {
            $this->usuarios->registrarFalloDeLogin($id);
        }

        // Simular que el primer fallo fue hace 16 minutos (moviendo la fecha en la base).
        self::$db->exec("UPDATE usuario SET primer_fallo_en = NOW() - INTERVAL 16 MINUTE WHERE id = $id");

        // Este fallo empieza una serie nueva: cuenta como el 1°, no como el 5°.
        $this->usuarios->registrarFalloDeLogin($id);

        $this->assertFalse($this->usuarios->estaBloqueado($id));
        $this->assertSame(1, (int) self::$db->query("SELECT intentos_fallidos FROM usuario WHERE id = $id")->fetchColumn());
    }


    // Hashea con bcrypt de costo 4, el mínimo: en las pruebas no importa que sea lento a propósito,
    // y con costo 12 cada prueba tardaría bastante más. En la aplicación siempre es costo 12.
    private static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    }
}
