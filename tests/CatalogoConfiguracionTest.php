<?php

use PHPUnit\Framework\TestCase;

// El catálogo de la configuración de la veterinaria: qué valores acepta y cómo los muestra.
// Guardar contra la base lo prueba 11_configuracion.sh del Gateway.
final class CatalogoConfiguracionTest extends TestCase
{
    public function testSinNadaGuardadoTodosTienenSuValorPorDefecto(): void
    {
        $lista = CatalogoConfiguracion::listar([]);

        $this->assertCount(count(CatalogoConfiguracion::PARAMETROS), $lista);
        $apertura = $lista[0];
        $this->assertSame('hora_apertura', $apertura['clave']);
        $this->assertSame('08:00', $apertura['valor']);
        $this->assertFalse($apertura['modificado']);
        $this->assertNull($apertura['actualizado_por']);
    }

    public function testLoGuardadoSeMuestraConQuienLoCambio(): void
    {
        $lista = CatalogoConfiguracion::listar([
            'dias_aviso_vacuna' => ['valor' => '5', 'actualizado_en' => '2026-11-20 10:00:00', 'actualizado_por' => 'Vet'],
        ]);
        $vacuna = array_values(array_filter($lista, fn ($p) => $p['clave'] === 'dias_aviso_vacuna'))[0];

        $this->assertSame(5, $vacuna['valor']);
        $this->assertSame(7, $vacuna['defecto']);
        $this->assertTrue($vacuna['modificado']);
        $this->assertSame('Vet', $vacuna['actualizado_por']);
    }

    public function testUnValorGuardadoFueraDeRangoSeIgnora(): void
    {
        $lista = CatalogoConfiguracion::listar([
            'limite_stock_bajo' => ['valor' => '5000', 'actualizado_en' => '2026-11-20 10:00:00', 'actualizado_por' => 'Vet'],
        ]);
        $stock = array_values(array_filter($lista, fn ($p) => $p['clave'] === 'limite_stock_bajo'))[0];

        $this->assertSame(10, $stock['valor']);
        $this->assertFalse($stock['modificado']);
    }

    public function testValoresValidosQuedanComoTexto(): void
    {
        [$limpios, $errores] = CatalogoConfiguracion::validar(
            ['hora_apertura' => '09:00', 'dias_aviso_vacuna' => 5, 'limite_stock_bajo' => '3'],
            []
        );

        $this->assertSame([], $errores);
        $this->assertSame(['hora_apertura' => '09:00', 'dias_aviso_vacuna' => '5', 'limite_stock_bajo' => '3'], $limpios);
    }

    public function testCadaValorMalTieneSuError(): void
    {
        [$limpios, $errores] = CatalogoConfiguracion::validar(
            ['hora_apertura' => '9:00', 'dias_aviso_vacuna' => 0, 'dias_liquidar' => 1.5, 'no_existe' => 1],
            []
        );

        $this->assertSame([], $limpios);
        $this->assertSame(['hora_apertura', 'dias_aviso_vacuna', 'dias_liquidar', 'no_existe'], array_keys($errores));
        $this->assertSame('Tiene que ser un número entero entre 1 y 30', $errores['dias_aviso_vacuna']);
    }

    public function testHayQueMandarAlMenosUnValor(): void
    {
        foreach ([null, [], ['09:00'], 'hora_apertura'] as $valores) {
            [, $errores] = CatalogoConfiguracion::validar($valores, []);
            $this->assertSame(['valores'], array_keys($errores));
        }
    }

    public function testElCierreTieneQueSerDespuesDeLaAperturaGuardada(): void
    {
        // Ya está guardada la apertura a las 10: un cierre a las 09:30 no puede ser.
        [, $errores] = CatalogoConfiguracion::validar(['hora_cierre' => '09:30'], ['hora_apertura' => '10:00']);
        $this->assertSame(['hora_cierre'], array_keys($errores));

        // Si se cambia solo la apertura y queda después del cierre, el error va en la apertura.
        [, $errores] = CatalogoConfiguracion::validar(['hora_apertura' => '21:00'], []);
        $this->assertSame(['hora_apertura'], array_keys($errores));
    }

    public function testElSegundoControlTieneQueSerDespuesDelPrimero(): void
    {
        [, $errores] = CatalogoConfiguracion::validar(['dias_control_adopcion_1' => 90], []);
        $this->assertSame(['dias_control_adopcion_1'], array_keys($errores));

        [$limpios, $errores] = CatalogoConfiguracion::validar(['dias_control_adopcion_1' => 60, 'dias_control_adopcion_2' => 120], []);
        $this->assertSame([], $errores);
        $this->assertSame('120', $limpios['dias_control_adopcion_2']);
    }
}
