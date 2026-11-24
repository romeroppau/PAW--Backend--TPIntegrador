<?php

use PHPUnit\Framework\TestCase;

// Las reglas de una reseña (estrellas y texto) y cómo se muestra el autor. Sin base de datos.
class ResenaTest extends TestCase
{
    public function testUnaResenaValida(): void
    {
        [$limpios, $errores] = ValidadorResena::resena(['estrellas' => 5, 'texto' => '  Excelente atención.  ']);

        $this->assertSame([], $errores);
        $this->assertSame(['estrellas' => 5, 'texto' => 'Excelente atención.'], $limpios);

        // Las estrellas también pueden llegar como texto.
        [$limpios] = ValidadorResena::resena(['estrellas' => '3', 'texto' => 'Bien']);
        $this->assertSame(3, $limpios['estrellas']);
    }

    public function testEstrellasDe1A5YTextoObligatorio(): void
    {
        foreach ([0, 6, 'cinco', null, 4.5] as $mal) {
            [, $errores] = ValidadorResena::resena(['estrellas' => $mal, 'texto' => 'Bien']);
            $this->assertSame('Elegí de 1 a 5 estrellas', $errores['estrellas'] ?? null, var_export($mal, true));
        }

        [, $errores] = ValidadorResena::resena(['estrellas' => 4, 'texto' => '   ']);
        $this->assertSame('Escribí tu opinión', $errores['texto']);

        [, $errores] = ValidadorResena::resena(['estrellas' => 4, 'texto' => str_repeat('a', 1001)]);
        $this->assertSame('Puede tener hasta 1000 caracteres', $errores['texto']);
    }

    public function testLaRespuestaTieneQueTenerTexto(): void
    {
        [, $errores] = ValidadorResena::respuesta(['texto' => '']);
        $this->assertSame('Escribí la respuesta', $errores['texto']);

        [$limpios, $errores] = ValidadorResena::respuesta(['texto' => '¡Gracias!']);
        $this->assertSame([], $errores);
        $this->assertSame('¡Gracias!', $limpios['texto']);
    }

    public function testEnLaPaginaPublicaVaElNombreYLaInicialDelApellido(): void
    {
        $this->assertSame('Ana P.', ResenaRepositorio::nombrePublico('Ana Pérez'));
        $this->assertSame('Ana O.', ResenaRepositorio::nombrePublico('Ana María  Ortiz'));
        $this->assertSame('Lucía', ResenaRepositorio::nombrePublico('Lucía'));
        $this->assertSame('Cliente P.', ResenaRepositorio::nombrePublico('Cliente de Prueba'));
    }
}
