<?php

// La única clase que escribe SQL sobre la tabla configuracion (migración 014). Guarda solo los
// parámetros que la veterinaria cambió: volver al valor por defecto borra la fila.
// Los demás módulos la leen con comun/configuracion.php.
class ConfiguracionRepositorio
{
    public function __construct(private PDO $db)
    {
    }

    // clave => ['valor', 'actualizado_en', 'actualizado_por' (nombre de quien lo cambió)].
    public function guardados(): array
    {
        $filas = $this->db->query(
            'SELECT c.clave, c.valor, c.actualizado_en, u.nombre AS actualizado_por
             FROM configuracion c JOIN usuario u ON u.id = c.actualizado_por'
        )->fetchAll();

        $guardados = [];
        foreach ($filas as $fila) {
            $guardados[$fila['clave']] = $fila;
        }

        return $guardados;
    }

    // Guarda los valores ya validados, todos juntos. El que queda igual al valor por defecto se borra.
    public function guardar(array $valores, int $usuarioId, string $ahora): void
    {
        $insertar = $this->db->prepare(
            'INSERT INTO configuracion (clave, valor, actualizado_en, actualizado_por) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_en = VALUES(actualizado_en),
                                     actualizado_por = VALUES(actualizado_por)'
        );
        $borrar = $this->db->prepare('DELETE FROM configuracion WHERE clave = ?');

        $this->db->beginTransaction();
        try {
            foreach ($valores as $clave => $valor) {
                if ($valor === CatalogoConfiguracion::defecto($clave)) {
                    $borrar->execute([$clave]);
                } else {
                    $insertar->execute([$clave, $valor, $ahora, $usuarioId]);
                }
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }
}
