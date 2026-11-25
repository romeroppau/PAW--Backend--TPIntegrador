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
require_once __DIR__ . '/../src/RescatistaControlador.php';
require_once __DIR__ . '/../src/CuentaControlador.php';
require_once __DIR__ . '/../src/ClientesControlador.php';
require_once __DIR__ . '/../src/ValidadorResena.php';
require_once __DIR__ . '/../src/ResenaRepositorio.php';
require_once __DIR__ . '/../src/ResenaControlador.php';
require_once __DIR__ . '/../src/NotificacionRepositorio.php';
require_once __DIR__ . '/../src/NotificacionControlador.php';
require_once __DIR__ . '/../src/WhatsAppCliente.php';
require_once __DIR__ . '/../src/CatalogoConfiguracion.php';
require_once __DIR__ . '/../src/ConfiguracionRepositorio.php';
require_once __DIR__ . '/../src/ConfiguracionControlador.php';
require_once __DIR__ . '/../src/VerificacionWhatsApp.php';
require_once __DIR__ . '/../src/VerificacionRepositorio.php';
require_once __DIR__ . '/../src/VerificacionControlador.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 3. Crear el router y registrar las rutas.
$router = new Router();

$router->post(
    '/api/auth/login',
    [AuthControlador::class, 'login']
);

// Verificación del WhatsApp: el código para registrarse (público).
$router->post(
    '/api/auth/registro/codigo',
    [VerificacionControlador::class, 'codigoDelRegistro']
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

// Mis datos (cada usuario, la suya).
$router->get(
    '/api/cuenta',
    [CuentaControlador::class, 'ver']
);

$router->put(
    '/api/cuenta',
    [CuentaControlador::class, 'guardar']
);

$router->put(
    '/api/cuenta/contrasena',
    [CuentaControlador::class, 'cambiarContrasena']
);

// Cambiar el teléfono: código al número nuevo y confirmación (Verificación del WhatsApp).
$router->post(
    '/api/cuenta/telefono/codigo',
    [VerificacionControlador::class, 'codigoDelCambio']
);

$router->post(
    '/api/cuenta/telefono',
    [VerificacionControlador::class, 'confirmarCambio']
);

// Clientes (veterinario): la lista y la ficha de cada uno.
$router->get(
    '/api/clientes',
    [ClientesControlador::class, 'listar']
);

$router->get(
    '/api/clientes/{id}',
    [ClientesControlador::class, 'ver']
);

// Reseñas (se ven sin sesión; escriben clientes y rescatistas; responde la veterinaria).
$router->get(
    '/api/resenas',
    [ResenaControlador::class, 'listar']
);

$router->post(
    '/api/resenas',
    [ResenaControlador::class, 'crear']
);

$router->delete(
    '/api/resenas/{id}',
    [ResenaControlador::class, 'borrar']
);

$router->put(
    '/api/resenas/{id}/respuesta',
    [ResenaControlador::class, 'responder']
);

// Validación de rescatistas (veterinario).
$router->get(
    '/api/rescatistas',
    [RescatistaControlador::class, 'listar']
);

$router->get(
    '/api/rescatistas/{id}',
    [RescatistaControlador::class, 'ver']
);

$router->post(
    '/api/rescatistas/{id}/aprobar',
    [RescatistaControlador::class, 'aprobar']
);

$router->post(
    '/api/rescatistas/{id}/rechazar',
    [RescatistaControlador::class, 'rechazar']
);

$router->get(
    '/api/backend/salud',
    [AuthControlador::class, 'salud']
);
// Notificaciones.
$router->post(
    '/internal/notificaciones',
    [NotificacionControlador::class, 'crearInterno']
);

$router->get(
    '/api/notificaciones',
    [NotificacionControlador::class, 'listar']
);

$router->get(
    '/api/notificaciones/historial',
    [NotificacionControlador::class, 'historial']
);

$router->post(
    '/api/notificaciones/leidas',
    [NotificacionControlador::class, 'marcarTodasLeidas']
);

$router->post(
    '/api/notificaciones/{id}/leida',
    [NotificacionControlador::class, 'marcarLeida']
);

$router->post(
    '/api/notificaciones/tareas/enviar',
    [NotificacionControlador::class, 'enviarPendientes']
);

// Configuración de la veterinaria (parámetros que cambia desde su panel).
$router->get(
    '/api/configuracion',
    [ConfiguracionControlador::class, 'ver']
);

// Los datos de contacto de la veterinaria: pública, la lee el pie de todas las páginas.
$router->get(
    '/api/configuracion/contacto',
    [ConfiguracionControlador::class, 'contacto']
);

$router->put(
    '/api/configuracion',
    [ConfiguracionControlador::class, 'guardar']
);

// 4. Ejecutar el router y manejar errores inesperados.
try {
    $router->dispatch(
        Request::method(),
        Request::path()
    );
} catch (Throwable $error) {
    Logger::error(
        'Error inesperado en Backend: '
        . get_class($error)
        . ' - '
        . $error->getMessage()
    );

    Response::error(
        'Ocurrió un error interno del servidor',
        500
    );
}
