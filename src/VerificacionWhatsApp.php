<?php

// Las reglas de la verificación del WhatsApp (tabla verificacion_whatsapp, migración 019).
// Contrato: docs/api/contrato.md, sección "Verificación del WhatsApp". No usa la base: el SQL está
// en VerificacionRepositorio.
// - El código es de 6 números, vence a los 10 minutos y acepta hasta 5 intentos.
// - A un mismo número se le manda como mucho un código por minuto y 5 por hora: así nadie usa el
//   registro para llenar de mensajes un número ajeno (y no se gastan mensajes de WhatsApp).
class VerificacionWhatsApp
{
    public const MINUTOS_DE_VIGENCIA = 10;
    public const INTENTOS_MAXIMOS = 5;
    public const SEGUNDOS_ENTRE_ENVIOS = 60;
    public const ENVIOS_POR_HORA = 5;

    // Resultados de comprobar un código.
    public const OK = 'ok';
    public const SIN_CODIGO = 'sin_codigo';
    public const VENCIDO = 'vencido';
    public const AGOTADO = 'agotado';
    public const INCORRECTO = 'incorrecto';

    // Lo que ve la persona en cada caso (en "campos.codigo" de un 400).
    public const MENSAJES = [
        self::SIN_CODIGO => 'Primero pedí el código por WhatsApp',
        self::VENCIDO => 'El código venció. Pedí uno nuevo.',
        self::AGOTADO => 'Escribiste mal el código muchas veces. Pedí uno nuevo.',
        self::INCORRECTO => 'El código no es correcto',
    ];

    public const MENSAJE_FORMATO = 'Escribí el código de 6 números que te llegó por WhatsApp';

    // Un código nuevo de 6 números (puede empezar con 0).
    public static function generarCodigo(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // El texto que va en el WhatsApp (con la plantilla de avisos).
    public static function mensaje(string $codigo): string
    {
        return "tu código para verificar tu WhatsApp en HuellaVet es {$codigo}. Vence en "
            . self::MINUTOS_DE_VIGENCIA . ' minutos. Si no lo pediste, ignorá este mensaje.';
    }

    // El código que escribió la persona, sin espacios, si tiene 6 números; si no, null.
    public static function limpiarCodigo(mixed $codigo): ?string
    {
        if (!is_string($codigo) && !is_int($codigo)) {
            return null;
        }
        $limpio = preg_replace('/\s+/', '', (string) $codigo);

        return preg_match('/^\d{6}$/', $limpio) === 1 ? $limpio : null;
    }

    // Cuántos segundos hay que esperar para mandar otro código a ese número, o 0 si se puede ya.
    // $envios: las fechas de los códigos que se le mandaron en la última hora.
    public static function segundosDeEspera(array $envios, DateTimeImmutable $ahora): int
    {
        $espera = 0;

        $ultimo = $envios === [] ? null : max($envios);
        if ($ultimo !== null) {
            $espera = max($espera, $ultimo->getTimestamp() + self::SEGUNDOS_ENTRE_ENVIOS - $ahora->getTimestamp());
        }

        $haceUnaHora = $ahora->modify('-1 hour');
        $enLaHora = array_values(array_filter($envios, fn (DateTimeImmutable $f) => $f > $haceUnaHora));
        if (count($enLaHora) >= self::ENVIOS_POR_HORA) {
            sort($enLaHora);
            // Se libera un lugar cuando el más viejo de la hora cumple una hora.
            $espera = max($espera, $enLaHora[count($enLaHora) - self::ENVIOS_POR_HORA]->getTimestamp() + 3600 - $ahora->getTimestamp());
        }

        return max(0, $espera);
    }

    // Compara el código con el último que se mandó (la fila de la base, o null si no hay ninguno sin
    // usar). Devuelve OK, SIN_CODIGO, VENCIDO, AGOTADO o INCORRECTO.
    // $fila: ['codigo_hash' => ..., 'intentos' => int, 'vence_en' => DateTimeImmutable]
    public static function comprobar(?array $fila, string $codigo, DateTimeImmutable $ahora): string
    {
        if ($fila === null) {
            return self::SIN_CODIGO;
        }
        if ($fila['vence_en'] <= $ahora) {
            return self::VENCIDO;
        }
        if ($fila['intentos'] >= self::INTENTOS_MAXIMOS) {
            return self::AGOTADO;
        }

        return password_verify($codigo, $fila['codigo_hash']) ? self::OK : self::INCORRECTO;
    }
}
