<?php

// Las reseñas (migración 019 del Gateway). Contrato: docs/api/contrato-resenas.md.
class ResenaRepositorio
{
    public const ORDENES = [
        'recientes' => 'r.creada_en DESC, r.id DESC',
        'mejores' => 'r.estrellas DESC, r.creada_en DESC, r.id DESC',
        'peores' => 'r.estrellas ASC, r.creada_en DESC, r.id DESC',
    ];

    public function __construct(private PDO $db)
    {
    }

    // Las reseñas en el orden pedido, de a $porPagina. $estrellas: solo las de esa cantidad;
    // $minimo: solo las de esa cantidad o más (la portada pide 4). $usuarioId: quién mira (para
    // marcar las suyas), o null sin sesión. Devuelve [reseñas, hayMas].
    public function listar(string $orden, ?int $estrellas, ?int $minimo, int $pagina, int $porPagina, ?int $usuarioId): array
    {
        $sql = "SELECT r.id, r.autor_id, r.estrellas, r.texto,
                       DATE_FORMAT(r.creada_en, '%Y-%m-%d %H:%i:%s') AS creada_en,
                       r.respuesta, DATE_FORMAT(r.respondida_en, '%Y-%m-%d %H:%i:%s') AS respondida_en,
                       u.nombre AS autor
                FROM resena r JOIN usuario u ON u.id = r.autor_id
                WHERE 1 = 1";
        $valores = [];
        if ($estrellas !== null) {
            $sql .= ' AND r.estrellas = ?';
            $valores[] = $estrellas;
        }
        if ($minimo !== null) {
            $sql .= ' AND r.estrellas >= ?';
            $valores[] = $minimo;
        }
        $sql .= ' ORDER BY ' . self::ORDENES[$orden]
            . ' LIMIT ' . ($porPagina + 1) . ' OFFSET ' . (($pagina - 1) * $porPagina);

        $consulta = $this->db->prepare($sql);
        $consulta->execute($valores);
        $resenas = array_map(fn (array $f) => self::resena($f, $usuarioId), $consulta->fetchAll());

        return [array_slice($resenas, 0, $porPagina), count($resenas) > $porPagina];
    }

    // Una reseña, o null.
    public function buscar(int $id, ?int $usuarioId = null): ?array
    {
        $consulta = $this->db->prepare(
            "SELECT r.id, r.autor_id, r.estrellas, r.texto,
                    DATE_FORMAT(r.creada_en, '%Y-%m-%d %H:%i:%s') AS creada_en,
                    r.respuesta, DATE_FORMAT(r.respondida_en, '%Y-%m-%d %H:%i:%s') AS respondida_en,
                    u.nombre AS autor
             FROM resena r JOIN usuario u ON u.id = r.autor_id
             WHERE r.id = ?"
        );
        $consulta->execute([$id]);
        $fila = $consulta->fetch();

        return $fila === false ? null : self::resena($fila, $usuarioId);
    }

    // El resumen de arriba de la página (de todas, sin filtros), como Google: cuántas hay, el
    // promedio (con un decimal) y cuántas de cada cantidad de estrellas.
    public function resumen(): array
    {
        $porEstrellas = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
        $total = 0;
        $suma = 0;
        foreach ($this->db->query('SELECT estrellas, COUNT(*) AS cantidad FROM resena GROUP BY estrellas')->fetchAll() as $f) {
            $porEstrellas[(string) $f['estrellas']] = (int) $f['cantidad'];
            $total += (int) $f['cantidad'];
            $suma += (int) $f['estrellas'] * (int) $f['cantidad'];
        }

        return [
            'total' => $total,
            'promedio' => $total === 0 ? null : round($suma / $total, 1),
            'por_estrellas' => $porEstrellas,
        ];
    }

    public function crear(int $autorId, int $estrellas, string $texto, string $ahora): int
    {
        $this->db->prepare('INSERT INTO resena (autor_id, estrellas, texto, creada_en) VALUES (?, ?, ?, ?)')
            ->execute([$autorId, $estrellas, $texto, $ahora]);

        return (int) $this->db->lastInsertId();
    }

    // Borra la reseña solo si es de $autorId. true si la borró.
    public function borrar(int $id, int $autorId): bool
    {
        $consulta = $this->db->prepare('DELETE FROM resena WHERE id = ? AND autor_id = ?');
        $consulta->execute([$id, $autorId]);

        return $consulta->rowCount() > 0;
    }

    // Pone (o corrige) la respuesta de la veterinaria. true si la reseña existe.
    public function responder(int $id, int $veterinariaId, string $texto, string $ahora): bool
    {
        $consulta = $this->db->prepare('UPDATE resena SET respuesta = ?, respondida_en = ?, respondida_por = ? WHERE id = ?');
        $consulta->execute([$texto, $ahora, $veterinariaId, $id]);

        return $consulta->rowCount() > 0 || $this->buscar($id) !== null;
    }

    // Los ids de las veterinarias: reciben el aviso de cada reseña nueva.
    public function veterinarias(): array
    {
        return array_map('intval', $this->db->query("SELECT id FROM usuario WHERE rol = 'VETERINARIO'")->fetchAll(PDO::FETCH_COLUMN));
    }

    // "Ana María Pérez" -> "Ana P.": en la página pública va el nombre y la inicial del apellido.
    public static function nombrePublico(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre)) ?: [];
        if (count($partes) < 2) {
            return $partes[0] ?? '';
        }

        return $partes[0] . ' ' . mb_strtoupper(mb_substr($partes[count($partes) - 1], 0, 1)) . '.';
    }

    private static function resena(array $f, ?int $usuarioId): array
    {
        return [
            'id' => (int) $f['id'],
            'estrellas' => (int) $f['estrellas'],
            'texto' => $f['texto'],
            'autor' => self::nombrePublico($f['autor']),
            'fecha' => $f['creada_en'],
            // Si la escribió quien está mirando (para mostrarle "Borrar").
            'propia' => $usuarioId !== null && (int) $f['autor_id'] === $usuarioId,
            'respuesta' => $f['respuesta'] === null ? null : ['texto' => $f['respuesta'], 'fecha' => $f['respondida_en']],
        ];
    }
}
