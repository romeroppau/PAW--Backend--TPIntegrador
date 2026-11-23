<?php

// Valida una reseña nueva (estrellas y texto) y la respuesta de la veterinaria.
// Devuelve [datos limpios, errores por campo], como ValidadorRegistro.
class ValidadorResena
{
    public const TEXTO_MAXIMO = 1000;

    // { "estrellas": 1 a 5, "texto": "..." }
    public static function resena(array $datos): array
    {
        $limpios = [];
        $errores = [];

        $estrellas = $datos['estrellas'] ?? null;
        if (is_string($estrellas) && preg_match('/^[1-5]$/', $estrellas)) {
            $estrellas = (int) $estrellas;
        }
        if (!is_int($estrellas) || $estrellas < 1 || $estrellas > 5) {
            $errores['estrellas'] = 'Elegí de 1 a 5 estrellas';
        } else {
            $limpios['estrellas'] = $estrellas;
        }

        self::texto($datos, 'texto', 'Escribí tu opinión', $limpios, $errores);

        return [$limpios, $errores];
    }

    // { "texto": "..." }
    public static function respuesta(array $datos): array
    {
        $limpios = [];
        $errores = [];
        self::texto($datos, 'texto', 'Escribí la respuesta', $limpios, $errores);

        return [$limpios, $errores];
    }

    private static function texto(array $datos, string $campo, string $vacio, array &$limpios, array &$errores): void
    {
        $texto = is_string($datos[$campo] ?? null) ? trim($datos[$campo]) : '';

        if ($texto === '') {
            $errores[$campo] = $vacio;
        } elseif (mb_strlen($texto) > self::TEXTO_MAXIMO) {
            $errores[$campo] = 'Puede tener hasta ' . self::TEXTO_MAXIMO . ' caracteres';
        } else {
            $limpios[$campo] = $texto;
        }
    }
}
