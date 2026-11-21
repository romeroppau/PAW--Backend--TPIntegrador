<?php

class NotificacionRepositorio
{
    public function __construct(private PDO $db) {}

    /** true si se insertó; false si el id_operacion ya existía (aviso repetido). */
    public function insertar(
        int $usuarioId,
        string $tipo,
        string $canal,
        string $mensaje,
        string $fechaProgramada,
        string $idOperacion
    ): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO notificacion
                (usuario_id, tipo, canal, mensaje, fecha_programada, leida, id_operacion, creada_en)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)"
        );

        try {
            $stmt->execute([
                $usuarioId, $tipo, $canal, $mensaje, $fechaProgramada, $idOperacion,
                date('Y-m-d H:i:s'),   // hora de Argentina (ver date_default_timezone_set)
            ]);
        } catch (PDOException $e) {
            // 1062 = clave duplicada. Solo eso es "ya aplicado"; otros 23000 (clave foránea:
            // usuario inexistente) son error real y se propagan.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function existeUsuario(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM usuario WHERE id = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Los avisos de la campana: solo canal WEB, no leídos primero.
     * Los de WhatsApp quedan solo para el envío (un aviso por los dos canales son dos filas).
     */
    public function listarDeUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, tipo, canal, mensaje, fecha_programada, leida
             FROM notificacion
             WHERE usuario_id = ? AND canal = 'WEB'
             ORDER BY leida ASC, fecha_programada DESC, id DESC
             LIMIT 100"
        );
        $stmt->execute([$usuarioId]);

        return array_map(fn($f) => [
            'id' => (int) $f['id'],
            'tipo' => $f['tipo'],
            'canal' => $f['canal'],
            'mensaje' => $f['mensaje'],
            'fecha' => $f['fecha_programada'],
            'leida' => (bool) $f['leida'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Cuántos avisos web sin leer tiene el usuario (el número de la campana). */
    public function contarNoLeidas(int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM notificacion
             WHERE usuario_id = ? AND canal = 'WEB' AND leida = 0"
        );
        $stmt->execute([$usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    /** true si existía, era del usuario y es de canal WEB (marcarla dos veces también da true). */
    public function marcarLeida(int $id, int $usuarioId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM notificacion WHERE id = ? AND usuario_id = ? AND canal = 'WEB'"
        );
        $stmt->execute([$id, $usuarioId]);
        if (!$stmt->fetchColumn()) {
            return false;
        }

        $upd = $this->db->prepare(
            "UPDATE notificacion SET leida = 1 WHERE id = ? AND usuario_id = ? AND canal = 'WEB'"
        );
        $upd->execute([$id, $usuarioId]);
        return true;
    }

    /** Después de estos intentos el aviso deja de tomarse (queda solo en la web). */
    public const MAX_INTENTOS = 5;

    /**
     * Avisos de WhatsApp sin enviar, con nombre y teléfono del usuario. Los más viejos primero.
     * No toma los que ya agotaron los intentos: así no tapan a los nuevos.
     */
    public function pendientesWhatsApp(int $limite = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT n.id, n.mensaje, n.intentos, u.nombre, u.telefono
             FROM notificacion n
             JOIN usuario u ON u.id = n.usuario_id
             WHERE n.canal = 'WHATSAPP'
               AND n.enviada_en IS NULL
               AND n.intentos < ?
               AND n.fecha_programada <= ?
               AND n.fecha_programada >= ?
             ORDER BY n.fecha_programada ASC, n.id ASC
             LIMIT " . (int) $limite
        );
        // Solo los ya programados, y no más viejos de 3 días (un aviso de vacuna de hace una
        // semana ya no sirve).
        $stmt->execute([
            self::MAX_INTENTOS,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reserva el aviso para esta corrida sumándole un intento. false si otra corrida ya lo tomó
     * (o ya salió): así dos tareas superpuestas no mandan el mismo WhatsApp dos veces.
     */
    public function tomarParaEnviar(int $id, int $intentosLeidos): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notificacion SET intentos = intentos + 1
             WHERE id = ? AND enviada_en IS NULL AND intentos = ?"
        );
        $stmt->execute([$id, $intentosLeidos]);
        return $stmt->rowCount() === 1;
    }

    /** Marca el aviso como enviado (solo si todavía no lo estaba). */
    public function marcarEnviada(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE notificacion SET enviada_en = ?, ultimo_error = NULL
             WHERE id = ? AND enviada_en IS NULL"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    /**
     * Anota por qué no salió. Si es permanente, agota los intentos: no se vuelve a tomar.
     * Si no, queda para la próxima corrida (hasta MAX_INTENTOS).
     */
    public function registrarFallo(int $id, string $motivo, bool $permanente): void
    {
        // El intento ya se sumó en tomarParaEnviar(); permanente: se lleva al máximo.
        $minimo = $permanente ? self::MAX_INTENTOS : 0;
        $stmt = $this->db->prepare(
            "UPDATE notificacion SET ultimo_error = ?, intentos = GREATEST(intentos, ?)
             WHERE id = ? AND enviada_en IS NULL"
        );
        $stmt->execute([mb_substr($motivo, 0, 100), $minimo, $id]);
    }

    /** Devuelve el intento que se tomó (error de configuración: no fue culpa del aviso). */
    public function devolverIntento(int $id, string $motivo): void
    {
        $stmt = $this->db->prepare(
            "UPDATE notificacion SET intentos = GREATEST(intentos - 1, 0), ultimo_error = ?
             WHERE id = ? AND enviada_en IS NULL"
        );
        $stmt->execute([mb_substr($motivo, 0, 100), $id]);
    }
}
