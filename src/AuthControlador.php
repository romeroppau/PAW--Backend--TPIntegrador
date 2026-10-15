<?php

// Los endpoints de autenticación: un método por endpoint (contrato en docs/api/contrato.md).
// Por ahora tiene el login; los registros, /yo, el logout y la salud se agregan acá.
// Cada método lee el pedido, usa las otras clases y responde con Response::json (de comun/).
// Los métodos son static para poder conectarlos al router así: [AuthControlador::class, 'login'].
class AuthControlador
{
    // Mensaje único para cualquier falla del login: no revela si el email existe ni si la cuenta está bloqueada.
    private const ERROR_LOGIN = 'Email o contraseña incorrectos';
    // Mensaje cuando el email ya tiene cuenta. Revela que el email está registrado,
    // pero es un límite aceptado (SEGURIDAD.md): sin él, la persona no entendería por qué no puede registrarse.
    private const ERROR_EMAIL_REPETIDO = 'Ya existe una cuenta con ese email';


    // POST /api/auth/login
    public static function login(): void
    {
        // Request::json (de comun/) lee el cuerpo del pedido. Si no es un JSON válido, ya responde 400 y corta.
        $datos = Request::json();

        $email = $datos['email'] ?? '';
        $password = $datos['password'] ?? '';

        // Si falta alguno, o no es texto, 400. Acá no se usa "campos", para no dar más detalles.
        if (!is_string($email) || !is_string($password) || trim($email) === '' || $password === '') {
            Response::json(['error' => 'Ingresá tu email y tu contraseña'], 400);
            return;
        }

        // Los emails se guardan en minúsculas, así que se buscan en minúsculas.
        $email = strtolower(trim($email));

        $usuarios = new UsuarioRepositorio(Database::conectar());
        $usuario = $usuarios->buscarPorEmail($email);

        // El email no existe. Se registra en el log (auditoría de SEGURIDAD.md), nunca la contraseña.
        if ($usuario === null) {
            Logger::info("Login fallido: no existe el email $email");
            Response::json(['error' => self::ERROR_LOGIN], 401);
            return;
        }

        // La cuenta está bloqueada: mismo mensaje. Mientras dure el bloqueo, ni se revisa la contraseña.
        if ($usuarios->estaBloqueado($usuario->getId())) {
            Logger::info("Login fallido: cuenta bloqueada ($email)");
            Response::json(['error' => self::ERROR_LOGIN], 401);
            return;
        }

        // Contraseña incorrecta: se suma el fallo (y se bloquea si se llegó a 5).
        if (!$usuario->iniciarSesion($password)) {
            $usuarios->registrarFalloDeLogin($usuario->getId());
            Logger::info("Login fallido: contraseña incorrecta ($email)");
            Response::json(['error' => self::ERROR_LOGIN], 401);
            return;
        }

        // Contraseña correcta: se borran los fallos y se inicia la sesión (CSRF + JWT + cookie).
        $usuarios->reiniciarFallos($usuario->getId());

        Response::json(Sesion::iniciar($usuario), 200);
    }

        // GET /api/backend/salud
    // Indica si el módulo está funcionando. Además hace una consulta mínima a la base (SELECT 1):
    // así "funciona" quiere decir que también llega a MySQL, y si GitHub Actions visita esta ruta,
    // mantiene activa la base de Aiven (plan de la Entrega 3).
    public static function salud(): void
    {
        try {
            Database::conectar()->query('SELECT 1');
        } catch (Throwable $error) {
            // El detalle del error va al log; la respuesta no muestra nada interno.
            Logger::error('Salud de Backend: no se pudo conectar a la base: ' . $error->getMessage());
            Response::json(['estado' => 'sin conexión a la base', 'modulo' => 'Backend'], 503);
            return;
        }

        Response::json(['estado' => 'funciona', 'modulo' => 'Backend'], 200);
    }

        // POST /api/auth/registro/cliente
    // Crea una cuenta de cliente y deja la sesión iniciada.
    public static function registroCliente(): void
    {
        // 1. Validar. Si hay errores, 400 con un mensaje por campo.
        [$limpios, $errores] = ValidadorRegistro::cliente(Request::json());

        if ($errores !== []) {
            Response::json(['error' => 'Datos inválidos', 'campos' => $errores], 400);
            return;
        }

        // 2. ¿El email ya tiene cuenta?
        $usuarios = new UsuarioRepositorio(Database::conectar());

        if ($usuarios->buscarPorEmail($limpios['email']) !== null) {
            Response::json(['error' => self::ERROR_EMAIL_REPETIDO], 409);
            return;
        }

        // 3. Crear el cliente. El rol lo pone el repositorio (CLIENTE): el usuario no lo puede elegir.
        try {
            $id = $usuarios->insertarCliente(
                $limpios['nombre'],
                $limpios['email'],
                self::hashear($limpios['password']),
                $limpios['telefono']
            );
        } catch (PDOException $error) {
            if (self::esEmailRepetido($error)) {
                Response::json(['error' => self::ERROR_EMAIL_REPETIDO], 409);
                return;
            }
            throw $error; // cualquier otro error de la base: lo atrapa el 500 genérico
        }

        // 4. Iniciar la sesión del cliente nuevo y responder 201 (Created).
        Response::json(Sesion::iniciar($usuarios->buscarPorId($id)), 201);
    }

    // POST /api/auth/registro/rescatista
    // Crea una cuenta de rescatista en estado PENDIENTE y deja la sesión iniciada.
    // Es igual al registro de cliente, con la organización (opcional) y otro rol.
    public static function registroRescatista(): void
    {
        [$limpios, $errores] = ValidadorRegistro::rescatista(Request::json());

        if ($errores !== []) {
            Response::json(['error' => 'Datos inválidos', 'campos' => $errores], 400);
            return;
        }

        $usuarios = new UsuarioRepositorio(Database::conectar());

        if ($usuarios->buscarPorEmail($limpios['email']) !== null) {
            Response::json(['error' => self::ERROR_EMAIL_REPETIDO], 409);
            return;
        }

        // El repositorio lo crea con rol RESCATISTA y estado PENDIENTE: ninguno de los dos lo elige el usuario.
        try {
            $id = $usuarios->insertarRescatista(
                $limpios['nombre'],
                $limpios['email'],
                self::hashear($limpios['password']),
                $limpios['telefono'],
                $limpios['organizacion']
            );
        } catch (PDOException $error) {
            if (self::esEmailRepetido($error)) {
                Response::json(['error' => self::ERROR_EMAIL_REPETIDO], 409);
                return;
            }
            throw $error;
        }

        Response::json(Sesion::iniciar($usuarios->buscarPorId($id)), 201);
    }

        // GET /api/auth/yo
    // Devuelve quién está logueado. El frontend lo llama al cargar cada página:
    // con el rol arma el menú, y recupera el código CSRF (que se pierde al recargar la página).
    public static function yo(): void
    {
        // Auth (de comun/) verifica el JWT de la cookie. Con [] acepta cualquier rol.
        // Si no hay sesión válida, Auth ya responde 401 "No autenticado" y corta.
        $sesion = Auth::requerirRol([]);

        // Los datos (nombre, email) se leen de la base, porque el JWT solo trae id, rol y csrf.
        $usuario = (new UsuarioRepositorio(Database::conectar()))->buscarPorId((int) $sesion['id']);

        // El JWT es válido pero el usuario ya no existe en la base: se lo trata como sin sesión.
        if ($usuario === null) {
            Sesion::cerrar();
            Response::json(['error' => 'No autenticado'], 401);
            return;
        }

        // Se devuelve el MISMO csrf que está en el JWT de la cookie (no uno nuevo),
        // porque es contra ese que los módulos comparan el header X-CSRF-Token.
        Response::json(Sesion::datos($usuario, $sesion['csrf']), 200);
    }

    // POST /api/auth/logout
    // Cierra la sesión: borra la cookie. Exige el token CSRF, como todo POST con sesión.
    public static function logout(): void
    {
        $sesion = Auth::requerirRol([]);   // 401 si no hay sesión
        Auth::verificarCsrf($sesion);      // 403 si falta o no coincide el header X-CSRF-Token

        Sesion::cerrar();

        // 204 No Content: salió bien, y la respuesta no tiene cuerpo (el contrato lo dice así).
        // Por eso no se usa Response::json, que siempre manda un JSON.
        http_response_code(204);
    }


    // Hashea la contraseña con bcrypt de costo 12 (SEGURIDAD.md). Nunca se guarda en limpio.
    // password_hash agrega una "sal" aleatoria, así dos contraseñas iguales tienen hashes distintos.
    private static function hashear(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ¿La base rechazó el INSERT porque el email ya existe? MySQL lo indica con el código 23000
    // (se viola una regla de la tabla: acá, el UNIQUE del email).
    // Cubre el caso de dos registros con el mismo email en el mismo instante.
    private static function esEmailRepetido(PDOException $error): bool
    {
        return (string) $error->getCode() === '23000';
    }


}
