<?php

// Configuración de la veterinaria (Entrega 5): los parámetros que cambia desde su panel (horario de
// los turnos, días de los avisos, stock bajo…). Qué parámetros hay y sus reglas: CatalogoConfiguracion.
// Contrato: docs/api/contrato.md, sección "Configuración".
class ConfiguracionControlador
{
    // GET /api/configuracion — veterinaria. Todos los parámetros con su valor actual.
    public static function ver(): void
    {
        Auth::requerirRol(['VETERINARIO']);

        self::responder(new ConfiguracionRepositorio(Database::conectar()));
    }

    // PUT /api/configuracion — veterinaria. {"valores": {"hora_apertura": "09:00", "dias_aviso_vacuna": 5}}
    // Se guardan todos o ninguno. Mandar el valor por defecto lo vuelve a dejar como estaba.
    public static function guardar(): void
    {
        $sesion = Auth::requerirRol(['VETERINARIO']);
        Auth::verificarCsrf($sesion);

        $repositorio = new ConfiguracionRepositorio(Database::conectar());
        $actuales = array_map(fn ($g) => $g['valor'], $repositorio->guardados());
        [$valores, $errores] = CatalogoConfiguracion::validar(Request::json()['valores'] ?? null, $actuales);

        if ($errores !== []) {
            Response::error('Revisá los valores marcados', 400, $errores);
            return;
        }

        $repositorio->guardar($valores, (int) $sesion['id'], date('Y-m-d H:i:s'));
        Logger::info('Configuración: la veterinaria ' . $sesion['id'] . ' cambió ' . implode(', ', array_keys($valores)));

        self::responder($repositorio);
    }

    private static function responder(ConfiguracionRepositorio $repositorio): void
    {
        Response::json(['parametros' => CatalogoConfiguracion::listar($repositorio->guardados())], 200);
    }
}
