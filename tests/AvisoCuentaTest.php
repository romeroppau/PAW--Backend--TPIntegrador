<?php

use PHPUnit\Framework\TestCase;

// El aviso que recibe la rescatista cuando la veterinaria decide sobre su cuenta
// (contrato-adopciones.md, sección 8). Que llegue a la campana lo prueba 09_adopciones.sh.
final class AvisoCuentaTest extends TestCase
{
    public function testAprobadaLeDiceQueYaPuedePublicar(): void
    {
        $this->assertStringContainsString('fue aprobada', RescatistaControlador::mensajeDeCuenta('APROBADO'));
        $this->assertStringContainsString('Mis publicaciones', RescatistaControlador::mensajeDeCuenta('APROBADO'));
    }

    public function testRechazadaLeDiceQueNoVaAPoderPublicar(): void
    {
        $mensaje = RescatistaControlador::mensajeDeCuenta('RECHAZADO');

        $this->assertStringContainsString('no aprobó', $mensaje);
        $this->assertLessThanOrEqual(500, mb_strlen($mensaje));
    }
}
