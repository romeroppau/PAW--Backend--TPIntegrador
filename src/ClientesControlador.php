<?php

// Los clientes, para la veterinaria (Entrega 5): la lista con buscador (Panel → Mis pacientes →
// Clientes) y la ficha de cada uno, a la que se llega también desde el nombre del dueño en Mascotas y
// en la Agenda. Solo lectura: cada uno cambia sus datos desde "Mis datos".
// Contrato: docs/api/contrato.md, sección "Clientes".
class ClientesControlador
{
    private const POR_PAGINA = 20;
    private const BUSCAR_MAXIMO = 100;

    // GET /api/clientes?buscar=ana&pagina=1
    public static function listar(): void
    {
        Auth::requerirRol(['VETERINARIO']);

        $buscar = is_string($_GET['buscar'] ?? null) ? mb_substr(trim($_GET['buscar']), 0, self::BUSCAR_MAXIMO) : '';
        $pagina = $_GET['pagina'] ?? '1';
        if (!is_string($pagina) || !preg_match('/^[1-9]\d{0,5}$/', $pagina)) {
            Response::error('Datos inválidos', 400, ['pagina' => 'Tiene que ser un número entero desde 1']);
            return;
        }

        [$clientes, $hayMas] = (new UsuarioRepositorio(Database::conectar()))
            ->listarClientes($buscar, (int) $pagina, self::POR_PAGINA);

        Response::json([
            'datos' => $clientes,
            'pagina' => (int) $pagina,
            'por_pagina' => self::POR_PAGINA,
            'hay_mas' => $hayMas,
        ], 200);
    }

    // GET /api/clientes/{id}
    // La ficha de un dueño de mascotas: un cliente o un rescatista (los rescatistas también tienen
    // mascotas a cargo). De otra veterinaria, o de un id que no existe: 404.
    public static function ver(array $parametros): void
    {
        Auth::requerirRol(['VETERINARIO']);

        $id = $parametros['id'] ?? '';
        $cuenta = is_string($id) && ctype_digit($id)
            ? (new UsuarioRepositorio(Database::conectar()))->cuenta((int) $id)
            : null;

        if ($cuenta === null || !in_array($cuenta['rol'], ['CLIENTE', 'RESCATISTA'], true)) {
            Response::error('Cliente no encontrado', 404);
            return;
        }

        Response::json($cuenta, 200);
    }
}
