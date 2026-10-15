<?php

// Patrón repositorio: esta es la ÚNICA clase que escribe SQL sobre la tabla usuario.
// El resto del código le pide cosas ("buscá este email", "creá este cliente") sin saber nada de tablas.
// - Si cambia la tabla, se toca solo este archivo.
// - Todas las consultas son parametrizadas: los datos van separados del SQL, con "?",
//   y así se evita la inyección SQL (SEGURIDAD.md).
// - Convierte cada fila en un objeto Cliente, Veterinario o Rescatista, según la columna rol.
// - También maneja el bloqueo por intentos fallidos (intentos_fallidos, primer_fallo_en, bloqueado_hasta),
//   que está en el DER pero no en las clases del UML: es un dato de seguridad del acceso, no del usuario.
class UsuarioRepositorio
{
    // Recibe la conexión a la base (un objeto PDO). En la aplicación se le pasa Database::conectar() de comun/.
    // Recibirla de afuera, en vez de conectarse sola, permite usar otra base en las pruebas.
    public function __construct(private PDO $db)
    {
    }

    // Devuelve el usuario con ese email, o null si no existe. La usan el login, el registro y el script.
    public function buscarPorEmail(string $email): Cliente|Veterinario|Rescatista|null
    {
        $consulta = $this->db->prepare(
            'SELECT id, nombre, email, password_hash, telefono, rol, organizacion, estado_validacion
             FROM usuario WHERE email = ?'
        );
        $consulta->execute([$email]);

        return $this->crearDesdeFila($consulta->fetch());
    }

    // Devuelve el usuario con ese id, o null. La usa /yo, con el id que viene en el JWT.
    public function buscarPorId(int $id): Cliente|Veterinario|Rescatista|null
    {
        $consulta = $this->db->prepare(
            'SELECT id, nombre, email, password_hash, telefono, rol, organizacion, estado_validacion
             FROM usuario WHERE id = ?'
        );
        $consulta->execute([$id]);

        return $this->crearDesdeFila($consulta->fetch());
    }

    // Las tres funciones de alta reciben el hash de la contraseña, nunca la contraseña.
    // El rol lo pone esta clase según la función, nunca lo elige el usuario.
    // Devuelven el id que MySQL le asignó a la fila nueva (AUTO_INCREMENT).
    // Si el email ya existe, MySQL rechaza el INSERT por el UNIQUE y PDO lanza una PDOException
    // con código 23000: el endpoint de registro la atrapa y responde 409.

    public function insertarCliente(string $nombre, string $email, string $passwordHash, string $telefono): int
    {
        $consulta = $this->db->prepare(
            "INSERT INTO usuario (nombre, email, password_hash, telefono, rol)
             VALUES (?, ?, ?, ?, 'CLIENTE')"
        );
        $consulta->execute([$nombre, $email, $passwordHash, $telefono]);

        return (int) $this->db->lastInsertId();
    }

    // El rescatista siempre empieza en PENDIENTE.
    public function insertarRescatista(
        string $nombre,
        string $email,
        string $passwordHash,
        string $telefono,
        ?string $organizacion
    ): int {
        $consulta = $this->db->prepare(
            "INSERT INTO usuario (nombre, email, password_hash, telefono, organizacion, rol, estado_validacion)
             VALUES (?, ?, ?, ?, ?, 'RESCATISTA', 'PENDIENTE')"
        );
        $consulta->execute([$nombre, $email, $passwordHash, $telefono, $organizacion]);

        return (int) $this->db->lastInsertId();
    }

    // Solo lo usa el script de carga del veterinario. El veterinario no tiene teléfono.
    public function insertarVeterinario(string $nombre, string $email, string $passwordHash): int
    {
        $consulta = $this->db->prepare(
            "INSERT INTO usuario (nombre, email, password_hash, rol)
             VALUES (?, ?, ?, 'VETERINARIO')"
        );
        $consulta->execute([$nombre, $email, $passwordHash]);

        return (int) $this->db->lastInsertId();
    }

    // ---------- Bloqueo por intentos fallidos ----------
    // Regla (SEGURIDAD.md): 5 intentos fallidos en 15 minutos, contados desde el primer fallo,
    // bloquean la cuenta 15 minutos. Un login correcto reinicia el contador.
    // Todas las fechas las calcula MySQL con NOW(), así todas las comparaciones usan el mismo reloj.

    // ¿La cuenta está bloqueada en este momento?
    public function estaBloqueado(int $id): bool
    {
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) FROM usuario WHERE id = ? AND bloqueado_hasta > NOW()'
        );
        $consulta->execute([$id]);

        return (int) $consulta->fetchColumn() > 0;
    }

    // Registra un login fallido. Se hace en dos pasos:
    // 1) Si no había fallos, o el primero fue hace más de 15 minutos, empieza una serie nueva
    //    (intentos = 1, primer_fallo_en = ahora). Si no, suma 1 al contador.
    // 2) Si el contador llegó a 5, bloquea 15 minutos y deja el contador en cero.
    public function registrarFalloDeLogin(int $id): void
    {
        $serieNueva = 'primer_fallo_en IS NULL OR primer_fallo_en < NOW() - INTERVAL 15 MINUTE';

        $consulta = $this->db->prepare(
            "UPDATE usuario SET
                 intentos_fallidos = IF($serieNueva, 1, intentos_fallidos + 1),
                 primer_fallo_en = IF($serieNueva, NOW(), primer_fallo_en)
             WHERE id = ?"
        );
        $consulta->execute([$id]);

        $consulta = $this->db->prepare(
            'UPDATE usuario SET
                 bloqueado_hasta = NOW() + INTERVAL 15 MINUTE,
                 intentos_fallidos = 0,
                 primer_fallo_en = NULL
             WHERE id = ? AND intentos_fallidos >= 5'
        );
        $consulta->execute([$id]);
    }

    // Login correcto: borra los fallos y el bloqueo.
    public function reiniciarFallos(int $id): void
    {
        $consulta = $this->db->prepare(
            'UPDATE usuario SET intentos_fallidos = 0, primer_fallo_en = NULL, bloqueado_hasta = NULL
             WHERE id = ?'
        );
        $consulta->execute([$id]);
    }

    // Convierte una fila de la tabla en el objeto que corresponde según la columna rol.
    // Si la consulta no encontró nada, fetch() devuelve false y acá se devuelve null.
    // "match" es como un switch: según el valor de rol, crea una clase u otra.
    private function crearDesdeFila(array|false $fila): Cliente|Veterinario|Rescatista|null
    {
        if ($fila === false) {
            return null;
        }

        return match ($fila['rol']) {
            'CLIENTE' => new Cliente(
                (int) $fila['id'],
                $fila['nombre'],
                $fila['email'],
                $fila['password_hash'],
                (string) $fila['telefono']
            ),
            'VETERINARIO' => new Veterinario(
                (int) $fila['id'],
                $fila['nombre'],
                $fila['email'],
                $fila['password_hash']
            ),
            'RESCATISTA' => new Rescatista(
                (int) $fila['id'],
                $fila['nombre'],
                $fila['email'],
                $fila['password_hash'],
                (string) $fila['telefono'],
                $fila['organizacion'],
                $fila['estado_validacion']
            ),
        };
    }
}
