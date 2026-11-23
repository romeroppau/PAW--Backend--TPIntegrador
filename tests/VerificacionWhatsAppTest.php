<?php

use PHPUnit\Framework\TestCase;

// Pruebas de las reglas de la verificación del WhatsApp (src/VerificacionWhatsApp.php): el código,
// cuándo se puede mandar otro y la comprobación. No usan la base de datos.
class VerificacionWhatsAppTest extends TestCase
{
    private function fecha(string $texto): DateTimeImmutable
    {
        return new DateTimeImmutable($texto, new DateTimeZone('America/Argentina/Buenos_Aires'));
    }

    private function fila(string $codigo, int $intentos = 0, string $vence = '2026-11-23 10:10:00'): array
    {
        return ['codigo_hash' => password_hash($codigo, PASSWORD_DEFAULT), 'intentos' => $intentos, 'vence_en' => $this->fecha($vence)];
    }

    public function testElCodigoTieneSeisNumeros(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', VerificacionWhatsApp::generarCodigo());
        }
    }

    public function testElCodigoEscritoSeLimpiaDeEspacios(): void
    {
        $this->assertSame('012345', VerificacionWhatsApp::limpiarCodigo(' 012 345 '));
        $this->assertSame('123456', VerificacionWhatsApp::limpiarCodigo(123456));
        foreach (['12345', '1234567', 'abcdef', '', null, ['123456']] as $malo) {
            $this->assertNull(VerificacionWhatsApp::limpiarCodigo($malo), var_export($malo, true));
        }
    }

    public function testElMensajeLlevaElCodigoYCuantoDura(): void
    {
        $this->assertSame(
            'tu código para verificar tu WhatsApp en HuellaVet es 012345. Vence en 10 minutos. Si no lo pediste, ignorá este mensaje.',
            VerificacionWhatsApp::mensaje('012345')
        );
    }

    public function testUnCodigoPorMinuto(): void
    {
        $ahora = $this->fecha('2026-11-23 10:00:40');

        $this->assertSame(0, VerificacionWhatsApp::segundosDeEspera([], $ahora));
        $this->assertSame(20, VerificacionWhatsApp::segundosDeEspera([$this->fecha('2026-11-23 10:00:00')], $ahora));
        $this->assertSame(0, VerificacionWhatsApp::segundosDeEspera([$this->fecha('2026-11-23 09:59:40')], $ahora));
    }

    public function testCincoCodigosPorHora(): void
    {
        $ahora = $this->fecha('2026-11-23 10:30:00');
        $cuatro = [
            $this->fecha('2026-11-23 09:40:00'), $this->fecha('2026-11-23 09:50:00'),
            $this->fecha('2026-11-23 10:00:00'), $this->fecha('2026-11-23 10:10:00'),
        ];
        $this->assertSame(0, VerificacionWhatsApp::segundosDeEspera($cuatro, $ahora));

        // Con 5 en la última hora, hay que esperar a que el más viejo cumpla la hora (09:40 → 10:40).
        $cinco = [...$cuatro, $this->fecha('2026-11-23 10:20:00')];
        $this->assertSame(600, VerificacionWhatsApp::segundosDeEspera($cinco, $ahora));
    }

    public function testComprobarElCodigo(): void
    {
        $ahora = $this->fecha('2026-11-23 10:05:00');

        $this->assertSame(VerificacionWhatsApp::OK, VerificacionWhatsApp::comprobar($this->fila('123456'), '123456', $ahora));
        $this->assertSame(VerificacionWhatsApp::INCORRECTO, VerificacionWhatsApp::comprobar($this->fila('123456'), '654321', $ahora));
        $this->assertSame(VerificacionWhatsApp::SIN_CODIGO, VerificacionWhatsApp::comprobar(null, '123456', $ahora));
    }

    public function testUnCodigoVencidoNoSirveAunqueSeaElCorrecto(): void
    {
        $alVencer = $this->fecha('2026-11-23 10:10:00');

        $this->assertSame(VerificacionWhatsApp::VENCIDO, VerificacionWhatsApp::comprobar($this->fila('123456'), '123456', $alVencer));
    }

    public function testConCincoIntentosMalosHayQuePedirOtro(): void
    {
        $ahora = $this->fecha('2026-11-23 10:05:00');

        $this->assertSame(VerificacionWhatsApp::OK, VerificacionWhatsApp::comprobar($this->fila('123456', 4), '123456', $ahora));
        $this->assertSame(VerificacionWhatsApp::AGOTADO, VerificacionWhatsApp::comprobar($this->fila('123456', 5), '123456', $ahora));
    }
}
