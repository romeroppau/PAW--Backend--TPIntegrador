<?php

// Reseñas (Entrega 5). Contrato: docs/api/contrato-resenas.md.
// - Las ve cualquiera (sin sesión): la página "Reseñas" y la portada.
// - Las escriben clientes y rescatistas (todas las que quieran) y se publican enseguida. Cada autor
//   puede borrar las suyas.
// - La veterinaria las responde (no las borra). Le llega un aviso a la campana por cada reseña nueva.
class ResenaControlador
{
    private const AUTORES = ['CLIENTE', 'RESCATISTA'];
    private const POR_PAGINA = 3;

    // GET /api/resenas?orden=recientes&estrellas=5&minimo=4&pagina=1
    public static function listar(): void
    {
        $errores = [];

        $orden = $_GET['orden'] ?? 'recientes';
        if (!is_string($orden) || !array_key_exists($orden, ResenaRepositorio::ORDENES)) {
            $errores['orden'] = 'Tiene que ser recientes, mejores o peores';
        }
        $estrellas = self::estrellas($_GET['estrellas'] ?? null, 'estrellas', $errores);
        $minimo = self::estrellas($_GET['minimo'] ?? null, 'minimo', $errores);

        $pagina = $_GET['pagina'] ?? '1';
        if (!is_string($pagina) || !preg_match('/^[1-9]\d{0,5}$/', $pagina)) {
            $errores['pagina'] = 'Tiene que ser un número entero desde 1';
        }

        if ($errores !== []) {
            Response::error('Datos inválidos', 400, $errores);
            return;
        }

        // Sin sesión también se ve; con sesión se marcan las propias.
        $usuario = Auth::usuarioActual();
        $resenas = self::repositorio();
        [$datos, $hayMas] = $resenas->listar($orden, $estrellas, $minimo, (int) $pagina, self::POR_PAGINA, $usuario === null ? null : (int) $usuario['id']);

        Response::json([
            'resumen' => $resenas->resumen(),
            'datos' => $datos,
            'pagina' => (int) $pagina,
            'por_pagina' => self::POR_PAGINA,
            'hay_mas' => $hayMas,
        ], 200);
    }

    // POST /api/resenas   { "estrellas": 5, "texto": "..." }
    public static function crear(): void
    {
        $sesion = Auth::requerirRol(self::AUTORES);
        Auth::verificarCsrf($sesion);

        [$limpios, $errores] = ValidadorResena::resena(Request::json());
        if ($errores !== []) {
            Response::error('Datos inválidos', 400, $errores);
            return;
        }

        $resenas = self::repositorio();
        $ahora = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $id = $resenas->crear((int) $sesion['id'], $limpios['estrellas'], $limpios['texto'], $ahora);
        $resena = $resenas->buscar($id, (int) $sesion['id']);

        self::avisarALaVeterinaria($resenas, $resena, $ahora);

        Response::json($resena, 201);
    }

    // DELETE /api/resenas/{id}   (solo su autor)
    public static function borrar(array $parametros): void
    {
        $sesion = Auth::requerirRol(self::AUTORES);
        Auth::verificarCsrf($sesion);

        $id = self::id($parametros);
        // De otro o inexistente: el mismo 404, para no revelar de quién es.
        if ($id === null || !self::repositorio()->borrar($id, (int) $sesion['id'])) {
            Response::error('Reseña no encontrada', 404);
            return;
        }

        http_response_code(204);
    }

    // PUT /api/resenas/{id}/respuesta   { "texto": "..." }   (veterinaria; si ya tenía, la corrige)
    public static function responder(array $parametros): void
    {
        $sesion = Auth::requerirRol(['VETERINARIO']);
        Auth::verificarCsrf($sesion);

        $id = self::id($parametros);
        [$limpios, $errores] = ValidadorResena::respuesta(Request::json());
        if ($id === null) {
            Response::error('Reseña no encontrada', 404);
            return;
        }
        if ($errores !== []) {
            Response::error('Datos inválidos', 400, $errores);
            return;
        }

        $resenas = self::repositorio();
        if (!$resenas->responder($id, (int) $sesion['id'], $limpios['texto'], (new DateTimeImmutable())->format('Y-m-d H:i:s'))) {
            Response::error('Reseña no encontrada', 404);
            return;
        }

        Response::json($resenas->buscar($id), 200);
    }

    // ---------- Ayudas ----------

    // A cada veterinaria, un aviso en la campana (solo web). Si falla, la reseña queda igual.
    private static function avisarALaVeterinaria(ResenaRepositorio $resenas, array $resena, string $ahora): void
    {
        try {
            $avisos = new NotificacionRepositorio(Database::conectar());
            $texto = mb_strlen($resena['texto']) > 80 ? mb_substr($resena['texto'], 0, 80) . '…' : $resena['texto'];
            $mensaje = sprintf('⭐ Nueva reseña de %s (%d %s): "%s"', $resena['autor'], $resena['estrellas'],
                $resena['estrellas'] === 1 ? 'estrella' : 'estrellas', $texto);
            foreach ($resenas->veterinarias() as $veterinariaId) {
                $avisos->insertar($veterinariaId, 'RESENA', 'WEB', $mensaje, $ahora, "resena-{$resena['id']}-vet-$veterinariaId");
            }
        } catch (Throwable $error) {
            Logger::error('Backend: no se pudo avisar de la reseña nueva: ' . $error->getMessage());
        }
    }

    // 1 a 5 (o ausente: null). Si es inválido, anota el error.
    private static function estrellas(mixed $valor, string $campo, array &$errores): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!is_string($valor) || !preg_match('/^[1-5]$/', $valor)) {
            $errores[$campo] = 'Tiene que ser un número de 1 a 5';
            return null;
        }

        return (int) $valor;
    }

    private static function id(array $parametros): ?int
    {
        $id = $parametros['id'] ?? '';

        return is_string($id) && ctype_digit($id) && (int) $id > 0 ? (int) $id : null;
    }

    private static function repositorio(): ResenaRepositorio
    {
        return new ResenaRepositorio(Database::conectar());
    }
}
