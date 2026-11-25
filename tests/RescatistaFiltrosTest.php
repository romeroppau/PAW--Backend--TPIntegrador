<?php

use PHPUnit\Framework\TestCase;

// Los filtros de la lista de rescatistas y el motivo del rechazo (RescatistaControlador).
final class RescatistaFiltrosTest extends TestCase
{
    public function testSinFiltrosNoFiltraNada(): void
    {
        $this->assertSame([['buscar' => null, 'desde' => null, 'hasta' => null], []], RescatistaControlador::filtros([]));
        $this->assertSame([['buscar' => null, 'desde' => null, 'hasta' => null], []], RescatistaControlador::filtros(['buscar' => '  ', 'desde' => '']));
    }

    public function testBuscarYFechasValidas(): void
    {
        [$filtros, $errores] = RescatistaControlador::filtros(['buscar' => ' Patitas ', 'desde' => '2026-11-01', 'hasta' => '2026-11-24']);

        $this->assertSame([], $errores);
        $this->assertSame(['buscar' => 'Patitas', 'desde' => '2026-11-01', 'hasta' => '2026-11-24'], $filtros);
    }

    public function testRechazaFechasInvalidasYDesdeDespuesDeHasta(): void
    {
        $this->assertArrayHasKey('desde', RescatistaControlador::filtros(['desde' => '2026-02-30'])[1]);
        $this->assertArrayHasKey('hasta', RescatistaControlador::filtros(['hasta' => '24/11/2026'])[1]);
        $this->assertArrayHasKey('desde', RescatistaControlador::filtros(['desde' => ['2026-11-01']])[1]);
        $this->assertArrayHasKey('hasta', RescatistaControlador::filtros(['desde' => '2026-11-24', 'hasta' => '2026-11-01'])[1]);
        $this->assertArrayHasKey('buscar', RescatistaControlador::filtros(['buscar' => str_repeat('a', 61)])[1]);
    }

    public function testElMotivoEsObligatorioYDe3A255Letras(): void
    {
        $this->assertSame(['Datos falsos', null], RescatistaControlador::motivo('  Datos falsos '));
        $this->assertNull(RescatistaControlador::motivo(null)[0]);
        $this->assertNull(RescatistaControlador::motivo('   ')[0]);
        $this->assertNull(RescatistaControlador::motivo('no')[0]);
        $this->assertNull(RescatistaControlador::motivo(str_repeat('ñ', 256))[0]);
        $this->assertNull(RescatistaControlador::motivo(['x'])[0]);
        $this->assertSame(str_repeat('ñ', 255), RescatistaControlador::motivo(str_repeat('ñ', 255))[0]);
    }
}
