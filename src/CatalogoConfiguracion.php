<?php

// Catálogo de los parámetros que la veterinaria puede cambiar desde su panel (Entrega 5).
// Acá está qué parámetros existen, de qué tipo son, entre qué valores pueden estar y cuál es su valor
// por defecto. La tabla configuracion (migración 014) guarda solo los que la veterinaria cambió.
//
// Quién usa cada uno lo lee con comun/configuracion.php, pasando el mismo valor por defecto que figura
// acá: si se cambia un valor por defecto, hay que cambiarlo en los dos lados.
//
// Para sumar un parámetro: agregarlo a PARAMETROS (sin migración) y leerlo en el módulo que lo usa.
// No usa la base de datos: se prueba con tests/CatalogoConfiguracionTest.php.
class CatalogoConfiguracion
{
    public const GRUPOS = [
        'TURNOS' => 'Turnos',
        'AVISOS' => 'Avisos a los clientes',
        'STOCK' => 'Stock y vencimientos',
        'TIENDA' => 'Tienda',
    ];

    // tipo HORA: "HH:MM" (minimo y maximo también son horas). tipo ENTERO: número entero, con su unidad.
    public const PARAMETROS = [
        'hora_apertura' => [
            'grupo' => 'TURNOS', 'nombre' => 'Apertura',
            'ayuda' => 'Desde qué hora se pueden dar turnos.',
            'tipo' => 'HORA', 'defecto' => '08:00', 'minimo' => '06:00', 'maximo' => '22:00', 'unidad' => null,
            'usa' => 'Pacientes',
        ],
        'hora_cierre' => [
            'grupo' => 'TURNOS', 'nombre' => 'Cierre',
            'ayuda' => 'El último turno tiene que empezar antes de esta hora.',
            'tipo' => 'HORA', 'defecto' => '20:00', 'minimo' => '07:00', 'maximo' => '23:59', 'unidad' => null,
            'usa' => 'Pacientes',
        ],
        'dias_aviso_vacuna' => [
            'grupo' => 'AVISOS', 'nombre' => 'Aviso de vacuna próxima',
            'ayuda' => 'Con cuántos días de anticipación se le avisa al cliente que a su mascota le toca una vacuna.',
            'tipo' => 'ENTERO', 'defecto' => 7, 'minimo' => 1, 'maximo' => 30, 'unidad' => 'días',
            'usa' => 'Reportes',
        ],
        'dias_control_adopcion_1' => [
            'grupo' => 'AVISOS', 'nombre' => 'Primer control de adopción',
            'ayuda' => 'A los cuántos días de completada una adopción se le recuerda al adoptante el primer control.',
            'tipo' => 'ENTERO', 'defecto' => 30, 'minimo' => 7, 'maximo' => 180, 'unidad' => 'días',
            'usa' => 'Reportes',
        ],
        'dias_control_adopcion_2' => [
            'grupo' => 'AVISOS', 'nombre' => 'Segundo control de adopción',
            'ayuda' => 'A los cuántos días se le recuerda el segundo control. Tiene que ser después del primero.',
            'tipo' => 'ENTERO', 'defecto' => 90, 'minimo' => 14, 'maximo' => 365, 'unidad' => 'días',
            'usa' => 'Reportes',
        ],
        'limite_stock_bajo' => [
            'grupo' => 'STOCK', 'nombre' => 'Stock bajo',
            'ayuda' => 'Con cuántas unidades o menos un producto o insumo se considera con stock bajo (aviso diario y reportes).',
            'tipo' => 'ENTERO', 'defecto' => 10, 'minimo' => 0, 'maximo' => 1000, 'unidad' => 'unidades',
            'usa' => 'Reportes',
        ],
        'dias_liquidar' => [
            'grupo' => 'STOCK', 'nombre' => 'Productos por vencer',
            'ayuda' => 'Cuántos días antes del vencimiento se sugiere liquidar un producto con descuento.',
            'tipo' => 'ENTERO', 'defecto' => 30, 'minimo' => 1, 'maximo' => 180, 'unidad' => 'días',
            'usa' => 'Reportes',
        ],
        // Tienda (Inventario). Un cambio vale para las compras nuevas: cada compra guarda sus plazos
        // al confirmarse (migración 015), así a las que ya existen no se les cambia lo prometido.
        'minutos_para_pagar' => [
            'grupo' => 'TIENDA', 'nombre' => 'Tiempo para pagar',
            'ayuda' => 'Cuántos minutos tiene el cliente para pagar con Mercado Pago después de tocar "Ir a pagar". Si no paga, la compra se cancela sola y los productos vuelven a la tienda.',
            'tipo' => 'ENTERO', 'defecto' => 15, 'minimo' => 5, 'maximo' => 60, 'unidad' => 'minutos',
            'usa' => 'Inventario',
        ],
        'dias_habiles_para_retirar' => [
            'grupo' => 'TIENDA', 'nombre' => 'Plazo para retirar',
            'ayuda' => 'Cuántos días hábiles (lunes a viernes) tiene el cliente para retirar un pedido pagado. Si no lo retira, se cancela sin reintegro.',
            'tipo' => 'ENTERO', 'defecto' => 5, 'minimo' => 2, 'maximo' => 15, 'unidad' => 'días hábiles',
            'usa' => 'Inventario',
        ],
        'hora_avisos_tienda' => [
            'grupo' => 'TIENDA', 'nombre' => 'Hora de los avisos de la tienda',
            'ayuda' => 'Desde qué hora salen los recordatorios para retirar y se cancelan los pedidos que no se retiraron a tiempo.',
            'tipo' => 'HORA', 'defecto' => '09:00', 'minimo' => '07:00', 'maximo' => '20:00', 'unidad' => null,
            'usa' => 'Inventario',
        ],
        'dia_primer_recordatorio' => [
            'grupo' => 'TIENDA', 'nombre' => 'Primer recordatorio para retirar',
            'ayuda' => 'En qué día hábil después de la compra se le recuerda al cliente que retire su pedido. El último recordatorio sale siempre el día hábil anterior al último día.',
            'tipo' => 'ENTERO', 'defecto' => 2, 'minimo' => 1, 'maximo' => 13, 'unidad' => 'día hábil',
            'usa' => 'Inventario',
        ],
    ];

    // Los parámetros con su valor actual, en el orden del catálogo, para GET /api/configuracion.
    // $guardados: clave => ['valor' => '09:00', 'actualizado_en' => '…', 'actualizado_por' => 'Nombre'].
    // Un valor guardado que ya no es válido (por ejemplo, porque cambió el rango) se ignora.
    public static function listar(array $guardados): array
    {
        $lista = [];

        foreach (self::PARAMETROS as $clave => $p) {
            $guardado = $guardados[$clave] ?? null;
            $valor = $guardado === null ? null : self::interpretar($p, $guardado['valor']);
            $modificado = $valor !== null;

            $lista[] = [
                'clave' => $clave,
                'grupo' => $p['grupo'],
                'nombre_grupo' => self::GRUPOS[$p['grupo']],
                'nombre' => $p['nombre'],
                'ayuda' => $p['ayuda'],
                'tipo' => $p['tipo'],
                'unidad' => $p['unidad'],
                'minimo' => $p['minimo'],
                'maximo' => $p['maximo'],
                'defecto' => $p['defecto'],
                'valor' => $modificado ? $valor : $p['defecto'],
                'modificado' => $modificado,
                'actualizado_en' => $modificado ? $guardado['actualizado_en'] : null,
                'actualizado_por' => $modificado ? $guardado['actualizado_por'] : null,
            ];
        }

        return $lista;
    }

    // Valida los valores nuevos de PUT /api/configuracion ({"valores": {"hora_apertura": "09:00"}}).
    // $actuales: clave => valor de lo que ya está guardado, para las reglas entre dos parámetros
    // (la apertura antes del cierre; el primer control antes del segundo).
    // Devuelve [limpios, errores]: limpios es clave => valor como texto, listo para guardar.
    public static function validar(mixed $valores, array $actuales): array
    {
        if (!is_array($valores) || $valores === [] || array_is_list($valores)) {
            return [[], ['valores' => 'Mandá al menos un parámetro con su valor']];
        }

        $limpios = [];
        $errores = [];

        foreach ($valores as $clave => $valor) {
            $p = self::PARAMETROS[$clave] ?? null;
            if ($p === null) {
                $errores[$clave] = 'No es un parámetro que se pueda configurar';
                continue;
            }

            $interpretado = self::interpretar($p, $valor);
            if ($interpretado === null) {
                $errores[$clave] = self::mensajeDeRango($p);
                continue;
            }

            $limpios[$clave] = (string) $interpretado;
        }

        if ($errores !== []) {
            return [[], $errores];
        }

        // Reglas entre dos parámetros, con cómo quedaría todo después de guardar: lo nuevo, si no lo
        // guardado y, si tampoco, el valor por defecto.
        $final = [];
        foreach (self::PARAMETROS as $clave => $p) {
            $final[$clave] = $limpios[$clave] ?? (string) (self::interpretar($p, $actuales[$clave] ?? null) ?? $p['defecto']);
        }

        // El error va en el campo que se cambió (si se cambiaron los dos, en el segundo).
        if ($final['hora_apertura'] >= $final['hora_cierre']) {
            $campo = isset($limpios['hora_cierre']) || !isset($limpios['hora_apertura']) ? 'hora_cierre' : 'hora_apertura';
            $errores[$campo] = 'El cierre tiene que ser después de la apertura';
        }
        // El primer recordatorio tiene que salir antes que el último (que es el día hábil anterior al
        // último día para retirar): al menos 2 días antes del final del plazo.
        if ((int) $final['dia_primer_recordatorio'] > (int) $final['dias_habiles_para_retirar'] - 2) {
            $campo = isset($limpios['dia_primer_recordatorio']) || !isset($limpios['dias_habiles_para_retirar'])
                ? 'dia_primer_recordatorio' : 'dias_habiles_para_retirar';
            $errores[$campo] = 'El primer recordatorio tiene que ser al menos 2 días hábiles antes del último día para retirar';
        }
        if ((int) $final['dias_control_adopcion_1'] >= (int) $final['dias_control_adopcion_2']) {
            $campo = isset($limpios['dias_control_adopcion_2']) || !isset($limpios['dias_control_adopcion_1'])
                ? 'dias_control_adopcion_2' : 'dias_control_adopcion_1';
            $errores[$campo] = 'El segundo control tiene que ser después del primero';
        }

        return $errores === [] ? [$limpios, []] : [[], $errores];
    }

    public static function defecto(string $clave): string
    {
        return (string) self::PARAMETROS[$clave]['defecto'];
    }

    // El valor con su tipo (int o "HH:MM") si es válido y está en el rango; si no, null.
    // Acepta el número como entero (JSON) o como texto (lo guardado en la tabla).
    private static function interpretar(array $p, mixed $valor): int|string|null
    {
        if ($p['tipo'] === 'HORA') {
            if (!is_string($valor) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor)) {
                return null;
            }
            return $valor >= $p['minimo'] && $valor <= $p['maximo'] ? $valor : null;
        }

        if (is_string($valor) && preg_match('/^\d{1,9}$/', $valor)) {
            $valor = (int) $valor;
        }
        if (!is_int($valor)) {
            return null;
        }

        return $valor >= $p['minimo'] && $valor <= $p['maximo'] ? $valor : null;
    }

    private static function mensajeDeRango(array $p): string
    {
        if ($p['tipo'] === 'HORA') {
            return "Elegí una hora entre las {$p['minimo']} y las {$p['maximo']}";
        }

        return "Tiene que ser un número entero entre {$p['minimo']} y {$p['maximo']}";
    }
}
