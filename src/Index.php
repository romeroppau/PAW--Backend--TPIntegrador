
<?php

// 1. Cargar las herramientas compartidas.
$raiz = dirname(__DIR__, 2);

require_once $raiz . '/comun/config.php';
require_once $raiz . '/comun/response.php';
require_once $raiz . '/comun/request.php';
require_once $raiz . '/comun/router.php';
require_once $raiz . '/comun/logger.php';
require_once $raiz . '/comun/database.php';
require_once $raiz . '/comun/auth.php';
require_once $raiz . '/comun/validator.php';

// 2. Cargar las clases propias del Backend.
//require_once __DIR__ . '/../src/Usuario.php';
require_once __DIR__ . '/../src/Cliente.php';
require_once __DIR__ . '/../src/Veterinario.php';
require_once __DIR__ . '/../src/Rescatista.php';
require_once __DIR__ . '/../src/UsuarioRepositorio.php';
require_once __DIR__ . '/../src/ValidadorRegistro.php';
require_once __DIR__ . '/../src/Jwt.php';
require_once __DIR__ . '/../src/Sesion.php';
require_once __DIR__ . '/../src/AuthControlador.php';

// 3. Crear el router y registrar las rutas.
$router = new Router();

$router->post(
    '/api/auth/login',
    [AuthControlador::class, 'login']
);

$router->post(
    '/api/auth/registro/cliente',
    [AuthControlador::class, 'registroCliente']
);

$router->post(
    '/api/auth/registro/rescatista',
    [AuthControlador::class, 'registroRescatista']
);

$router->get(
    '/api/auth/yo',
    [AuthControlador::class, 'yo']
);

$router->post(
    '/api/auth/logout',
    [AuthControlador::class, 'logout']
);

$router->get(
    '/api/backend/salud',
    [AuthControlador::class, 'salud']
);

// 4. Ejecutar el router y manejar errores inesperados.
try {
    $router->dispatch(
        Request::method(),
        Request::path()
    );
} catch (Throwable $error) {
    Logger::error(
        'Error inesperado en Backend: ' . get_class($error)
    );

    Response::error(
        'Ocurrió un error interno del servidor',
        500
    );
}
