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
        'CONTACTO' => 'Datos de la veterinaria',
    ];

    // tipo HORA: "HH:MM" (minimo y maximo también son horas). tipo ENTERO: número entero, con su unidad.
    // tipo TEXTO: los datos de contacto. minimo y maximo son el largo; formato dice qué se valida
    // (TEXTO libre, TELEFONO argentino o de otro país con +, EMAIL o URL de una red social, con su dominio en 'dominios').
    // Con minimo 0 se puede dejar vacío: la página no lo muestra.
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
        // Lo usa Pacientes: cada turno guarda el precio base al sacarse (migración 020), con el
        // descuento de su franja si está promocionada. La consulta toma ese valor y no se edita.
        'precio_consulta' => [
            'grupo' => 'TURNOS', 'nombre' => 'Precio de la consulta',
            'ayuda' => 'El valor base de la consulta, en pesos. Cada turno lo guarda al sacarse (con el descuento de su franja, si está en promoción): un cambio vale para los turnos nuevos.',
            'tipo' => 'ENTERO', 'defecto' => 15000, 'minimo' => 0, 'maximo' => 1000000, 'unidad' => 'pesos',
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
            'ayuda' => 'A los cuántos días hábiles de la compra se le recuerda al cliente que retire su pedido. El último recordatorio sale siempre el día hábil anterior al último día.',
            'tipo' => 'ENTERO', 'defecto' => 2, 'minimo' => 1, 'maximo' => 13, 'unidad' => 'días hábiles',
            'usa' => 'Inventario',
        ],
        // Reintegro cuando el comprador cancela un pedido ya pagado: más si avisa con anticipación.
        // Cada compra guarda esta política al confirmarse (migración 017), como los plazos.
        'porcentaje_reintegro_anticipado' => [
            'grupo' => 'TIENDA', 'nombre' => 'Reintegro si cancela con anticipación',
            'ayuda' => 'Qué porcentaje del importe se le devuelve al cliente que cancela un pedido pagado con la anticipación de abajo (o más) antes del final del último día para retirar.',
            'tipo' => 'ENTERO', 'defecto' => 75, 'minimo' => 0, 'maximo' => 100, 'unidad' => '%',
            'usa' => 'Inventario',
        ],
        'porcentaje_reintegro_ultimo_momento' => [
            'grupo' => 'TIENDA', 'nombre' => 'Reintegro si cancela a último momento',
            'ayuda' => 'Qué porcentaje se le devuelve si cancela con menos anticipación. Si no retira el pedido a tiempo, no se le devuelve nada.',
            'tipo' => 'ENTERO', 'defecto' => 50, 'minimo' => 0, 'maximo' => 100, 'unidad' => '%',
            'usa' => 'Inventario',
        ],
        'horas_anticipacion_reintegro' => [
            'grupo' => 'TIENDA', 'nombre' => 'Anticipación para el reintegro mayor',
            'ayuda' => 'Con cuántas horas de anticipación (antes del final del último día para retirar) tiene que cancelar el cliente para recibir el reintegro mayor.',
            'tipo' => 'ENTERO', 'defecto' => 48, 'minimo' => 1, 'maximo' => 168, 'unidad' => 'horas',
            'usa' => 'Inventario',
        ],
        // Datos de contacto: los muestran la portada, el pie, los paneles, Contactanos y el detalle de
        // producto (Frontend, con GET /api/configuracion/contacto, que es pública).
        'direccion' => [
            'grupo' => 'CONTACTO', 'nombre' => 'Dirección',
            'ayuda' => 'La dirección de la veterinaria, como la escribirías en Google Maps (se usa también para el mapa de Contactanos).',
            'tipo' => 'TEXTO', 'formato' => 'TEXTO', 'defecto' => 'Av. San Martín 1450, Luján', 'minimo' => 5, 'maximo' => 120, 'unidad' => null,
            'usa' => 'Frontend',
        ],
        'telefono' => [
            'grupo' => 'CONTACTO', 'nombre' => 'Teléfono',
            'ayuda' => 'El teléfono para llamar, con el código de área sin 0 y sin 15 (de otro país: con + y su código, por ejemplo +1 555 145 9000). Se muestra como lo escribas.',
            'tipo' => 'TEXTO', 'formato' => 'TELEFONO', 'defecto' => '11 3901-0486', 'minimo' => 10, 'maximo' => 20, 'unidad' => null,
            'usa' => 'Frontend',
        ],
        'whatsapp' => [
            'grupo' => 'CONTACTO', 'nombre' => 'WhatsApp',
            'ayuda' => 'El número al que escriben los clientes por WhatsApp, con el código de área sin 0 y sin 15 (de otro país: con + y su código, por ejemplo +1 555 145 9000).',
            'tipo' => 'TEXTO', 'formato' => 'TELEFONO', 'defecto' => '11 3901-0486', 'minimo' => 10, 'maximo' => 20, 'unidad' => null,
            'usa' => 'Frontend',
        ],
        'email_contacto' => [
            'grupo' => 'CONTACTO', 'nombre' => 'Email de contacto',
            'ayuda' => 'El email al que pueden escribir los clientes. Si lo dejás vacío, no se muestra.',
            'tipo' => 'TEXTO', 'formato' => 'EMAIL', 'defecto' => '', 'minimo' => 0, 'maximo' => 150, 'unidad' => null,
            'usa' => 'Frontend',
        ],
        'instagram' => [
            'grupo' => 'CONTACTO', 'nombre' => 'Instagram',
            'ayuda' => 'El enlace a la cuenta, por ejemplo https://www.instagram.com/huellavet. Si lo dejás vacío, no se muestra el ícono.',
            'tipo' => 'TEXTO', 'formato' => 'URL', 'dominios' => ['instagram.com'], 'defecto' => '', 'minimo' => 0, 'maximo' => 255, 'unidad' => null,
            'usa' => 'Frontend',
        ],
        'facebook' => [
            'grupo' => 'CONTACTO', 'nombre' => 'Facebook',
            'ayuda' => 'El enlace a la página, por ejemplo https://www.facebook.com/huellavet. Si lo dejás vacío, no se muestra el ícono.',
            'tipo' => 'TEXTO', 'formato' => 'URL', 'dominios' => ['facebook.com', 'fb.com'], 'defecto' => '', 'minimo' => 0, 'maximo' => 255, 'unidad' => null,
            'usa' => 'Frontend',
        ],
        'youtube' => [
            'grupo' => 'CONTACTO', 'nombre' => 'YouTube',
            'ayuda' => 'El enlace al canal, por ejemplo https://www.youtube.com/@huellavet. Si lo dejás vacío, no se muestra el ícono.',
            'tipo' => 'TEXTO', 'formato' => 'URL', 'dominios' => ['youtube.com', 'youtu.be'], 'defecto' => '', 'minimo' => 0, 'maximo' => 255, 'unidad' => null,
            'usa' => 'Frontend',
        ],
    ];

    // Las claves de los datos de contacto y cómo se llaman en GET /api/configuracion/contacto.
    private const CONTACTO = [
        'direccion' => 'direccion', 'telefono' => 'telefono', 'whatsapp' => 'whatsapp',
        'email_contacto' => 'email', 'instagram' => 'instagram', 'facebook' => 'facebook', 'youtube' => 'youtube',
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
                'formato' => $p['formato'] ?? null,
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
        // Cancelar a último momento nunca devuelve más que cancelar con anticipación.
        if ((int) $final['porcentaje_reintegro_ultimo_momento'] > (int) $final['porcentaje_reintegro_anticipado']) {
            $campo = isset($limpios['porcentaje_reintegro_ultimo_momento']) || !isset($limpios['porcentaje_reintegro_anticipado'])
                ? 'porcentaje_reintegro_ultimo_momento' : 'porcentaje_reintegro_anticipado';
            $errores[$campo] = 'El reintegro a último momento no puede ser mayor que el reintegro con anticipación';
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

    // Los datos de contacto para GET /api/configuracion/contacto (pública), con los enlaces ya armados
    // para llamar y para WhatsApp. Los vacíos van como null: la página no los muestra.
    // $guardados: lo mismo que recibe listar().
    public static function contacto(array $guardados): array
    {
        $valores = array_column(self::listar($guardados), 'valor', 'clave');
        $datos = [];
        foreach (self::CONTACTO as $clave => $nombre) {
            $datos[$nombre] = $valores[$clave] === '' ? null : $valores[$clave];
        }

        $datos['enlaces'] = [
            'telefono' => 'tel:+' . self::numeroCompleto($datos['telefono'], false),
            'whatsapp' => 'https://wa.me/' . self::numeroCompleto($datos['whatsapp'], true),
        ];

        return $datos;
    }

    // El número con el código de país y sin nada más, para los enlaces (tel:+…, wa.me/…), o null si
    // no es un teléfono válido:
    //   - argentino: 10 dígitos (código de área sin 0 y número sin 15), con +54 y 9 opcionales.
    //     WhatsApp lo quiere con el 9 de los celulares (549…); para llamar va sin él (54…).
    //   - de otro país: empieza con + y su código, de 8 a 15 dígitos en total (por ejemplo, el número
    //     de prueba de Meta, +1 555 145 9000).
    private static function numeroCompleto(string $telefono, bool $paraWhatsApp): ?string
    {
        $soloDigitos = preg_replace('/[\s\-().]/', '', $telefono);

        if (preg_match('/^(\+?54)?9?(\d{10})$/', $soloDigitos, $partes) === 1) {
            return ($paraWhatsApp ? '549' : '54') . $partes[2];
        }
        if (preg_match('/^\+([1-9]\d{7,14})$/', $soloDigitos, $partes) === 1 && !str_starts_with($partes[1], '54')) {
            return $partes[1];
        }

        return null;
    }

    // El valor con su tipo (int o "HH:MM") si es válido y está en el rango; si no, null.
    // Acepta el número como entero (JSON) o como texto (lo guardado en la tabla).
    private static function interpretar(array $p, mixed $valor): int|string|null
    {
        if ($p['tipo'] === 'TEXTO') {
            return self::interpretarTexto($p, $valor);
        }

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

    // Un dato de contacto: sin espacios de más y con el formato que pide. El email, en minúsculas.
    private static function interpretarTexto(array $p, mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }
        $valor = trim(preg_replace('/\s+/u', ' ', $valor));
        if ($valor === '') {
            return $p['minimo'] === 0 ? '' : null;
        }
        if (mb_strlen($valor) < $p['minimo'] || mb_strlen($valor) > $p['maximo']) {
            return null;
        }

        return match ($p['formato']) {
            'TELEFONO' => self::numeroCompleto($valor, true) !== null ? $valor : null,
            'EMAIL' => filter_var($valor, FILTER_VALIDATE_EMAIL) !== false ? strtolower($valor) : null,
            'URL' => self::esDeLaRed($valor, $p['dominios']) ? $valor : null,
            default => $valor,
        };
    }

    // Que sea https y del dominio de la red (con www., m. o lo que sea adelante).
    private static function esDeLaRed(string $url, array $dominios): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($dominios as $dominio) {
            if ($host === $dominio || str_ends_with($host, '.' . $dominio)) {
                return true;
            }
        }

        return false;
    }

    private static function mensajeDeRango(array $p): string
    {
        if ($p['tipo'] === 'TEXTO') {
            return match ($p['formato']) {
                'TELEFONO' => 'El teléfono no es válido. Ejemplo: 11 3901-0486 (de otro país, con + y su código: +1 555 145 9000)',
                'EMAIL' => 'El email no es válido',
                'URL' => 'Tiene que ser un enlace https de ' . $p['dominios'][0] . ', por ejemplo https://www.' . $p['dominios'][0] . '/huellavet',
                default => "Tiene que tener entre {$p['minimo']} y {$p['maximo']} caracteres",
            };
        }

        if ($p['tipo'] === 'HORA') {
            return "Elegí una hora entre las {$p['minimo']} y las {$p['maximo']}";
        }

        return "Tiene que ser un número entero entre {$p['minimo']} y {$p['maximo']}";
    }
}
