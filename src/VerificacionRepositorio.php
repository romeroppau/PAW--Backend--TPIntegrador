<?php

// El SQL de la verificación del WhatsApp (tabla verificacion_whatsapp, migración 019).
// Las reglas están en VerificacionWhatsApp. Todas las consultas son parametrizadas ("?").
// Las fechas se guardan en hora de Argentina (las calcula PHP, no MySQL, que trabaja en UTC).
class VerificacionRepositorio
{
    private const FORMATO = 'Y-m-d H:i:s';

    public function __construct(private PDO $db)
    {
    }

    // Las fechas de los códigos que se le mandaron a ese número en la última hora, para cualquier
    // propósito (el límite es por número: protege al dueño del número).
    public function enviosRecientes(string $telefono, DateTimeImmutable $ahora): array
    {
        $consulta = $this->db->prepare(
            'SELECT creado_en FROM verificacion_whatsapp WHERE telefono = ? AND creado_en > ?'
        );
        $consulta->execute([$telefono, $ahora->modify('-1 hour')->format(self::FORMATO)]);
        $zona = $ahora->getTimezone();

        return array_map(
            fn (string $fecha) => new DateTimeImmutable($fecha, $zona),
            $consulta->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    // Guarda el código que se acaba de mandar (hasheado). $usuarioId: quién cambia su número (CAMBIO).
    public function guardar(string $proposito, string $telefono, ?int $usuarioId, string $codigo, DateTimeImmutable $ahora): void
    {
        $this->db->prepare(
            'INSERT INTO verificacion_whatsapp (proposito, telefono, usuario_id, codigo_hash, creado_en, vence_en)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $proposito, $telefono, $usuarioId, password_hash($codigo, PASSWORD_DEFAULT),
            $ahora->format(self::FORMATO),
            $ahora->modify('+' . VerificacionWhatsApp::MINUTOS_DE_VIGENCIA . ' minutes')->format(self::FORMATO),
        ]);
    }

    // El último código sin usar para registrar ese número, o null. Bloquea la fila hasta el final de
    // la transacción: dos registros al mismo tiempo con el mismo código no lo usan los dos.
    public function ultimoDelRegistro(string $telefono, DateTimeZone $zona): ?array
    {
        $consulta = $this->db->prepare(
            "SELECT id, telefono, codigo_hash, intentos, vence_en FROM verificacion_whatsapp
             WHERE proposito = 'REGISTRO' AND telefono = ? AND usado_en IS NULL
             ORDER BY creado_en DESC, id DESC LIMIT 1 FOR UPDATE"
        );
        $consulta->execute([$telefono]);

        return $this->fila($consulta->fetch(), $zona);
    }

    // El último código sin usar del cambio de número de ese usuario (con el número nuevo), o null.
    public function ultimoDelCambio(int $usuarioId, DateTimeZone $zona): ?array
    {
        $consulta = $this->db->prepare(
            "SELECT id, telefono, codigo_hash, intentos, vence_en FROM verificacion_whatsapp
             WHERE proposito = 'CAMBIO' AND usuario_id = ? AND usado_en IS NULL
             ORDER BY creado_en DESC, id DESC LIMIT 1 FOR UPDATE"
        );
        $consulta->execute([$usuarioId]);

        return $this->fila($consulta->fetch(), $zona);
    }

    public function sumarIntento(int $id): void
    {
        $this->db->prepare('UPDATE verificacion_whatsapp SET intentos = intentos + 1 WHERE id = ?')->execute([$id]);
    }

    public function marcarUsado(int $id, DateTimeImmutable $ahora): void
    {
        $this->db->prepare('UPDATE verificacion_whatsapp SET usado_en = ? WHERE id = ?')
            ->execute([$ahora->format(self::FORMATO), $id]);
    }

    private function fila(array|false $fila, DateTimeZone $zona): ?array
    {
        if ($fila === false) {
            return null;
        }

        return [
            'id' => (int) $fila['id'],
            'telefono' => $fila['telefono'],
            'codigo_hash' => $fila['codigo_hash'],
            'intentos' => (int) $fila['intentos'],
            'vence_en' => new DateTimeImmutable($fila['vence_en'], $zona),
        ];
    }
}
