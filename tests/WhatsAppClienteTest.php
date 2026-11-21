<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Lo que decide WhatsAppCliente sin salir a internet: cómo normaliza el teléfono, la forma
// alternativa sin el 9 y qué errores de Meta se reintentan. El circuito entero (tarea de envío
// en modo simulado) lo prueba 10_notificaciones.sh.
final class WhatsAppClienteTest extends TestCase
{
    #[DataProvider('telefonosValidos')]
    public function testNormalizaLasFormasHabitualesDeAnotarElCelular(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, WhatsAppCliente::normalizarTelefono($entrada));
    }

    public static function telefonosValidos(): array
    {
        return [
            'internacional completo'    => ['+54 9 11 1234-5678', '5491112345678'],
            'sin el 9'                  => ['+54 11 1234 5678', '5491112345678'],
            'con 00 adelante'           => ['0054 9 11 1234 5678', '5491112345678'],
            'diez dígitos'              => ['1112345678', '5491112345678'],
            'con 0 y 15 (CABA)'         => ['011 15 1234-5678', '5491112345678'],
            'con 0 y 15 (Córdoba)'      => ['0351 15 123-4567', '5493511234567'],
            'con 0 y 15 (4 dígitos)'    => ['02944 15 12-3456', '5492944123456'],
            'internacional con 15'      => ['+54 351 15 123 4567', '5493511234567'],
            'sin 0 con 15 (Córdoba)'    => ['351 15 1234567', '5493511234567'],
        ];
    }

    #[DataProvider('telefonosInvalidos')]
    public function testRechazaLoQueNoEsUnCelularArgentino(string $entrada): void
    {
        $this->assertNull(WhatsAppCliente::normalizarTelefono($entrada));
    }

    public static function telefonosInvalidos(): array
    {
        return [
            'vacío'          => [''],
            'muy corto'      => ['1234'],
            'letras'         => ['no tengo'],
            '12 sin 15'      => ['351991234567'],
        ];
    }

    public function testLaFormaAlternativaEsSinEl9(): void
    {
        $this->assertSame('541112345678', WhatsAppCliente::formaAlternativa('5491112345678'));
        $this->assertNull(WhatsAppCliente::formaAlternativa('541112345678'));
    }

    public function testNumeroNoVerificadoEsPermanente(): void
    {
        $r = WhatsAppCliente::interpretarError(400, '{"error":{"code":131030,"message":"Recipient phone number not in allowed list"}}');

        $this->assertFalse($r->ok);
        $this->assertTrue($r->permanente);
        $this->assertSame(131030, $r->codigoMeta);
        $this->assertSame('META_131030_HTTP_400', $r->motivo);
    }

    public function testTokenVencidoEsDeConfiguracionYNoGastaElAviso(): void
    {
        $r = WhatsAppCliente::interpretarError(401, '{"error":{"code":190,"message":"Error validating access token"}}');

        $this->assertTrue($r->deConfiguracion);
        $this->assertFalse($r->permanente);
    }

    public function testPlantillaInexistenteEsDeConfiguracion(): void
    {
        $this->assertTrue(WhatsAppCliente::interpretarError(404, '{"error":{"code":132001}}')->deConfiguracion);
    }

    public function testErrorDelServidorOLimiteSeReintentan(): void
    {
        $caida = WhatsAppCliente::interpretarError(503, 'Service Unavailable');
        $limite = WhatsAppCliente::interpretarError(429, '{"error":{"code":130429}}');

        foreach ([$caida, $limite] as $r) {
            $this->assertFalse($r->ok);
            $this->assertFalse($r->permanente);
            $this->assertFalse($r->deConfiguracion);
        }
    }

    public function testEnModoSimuladoNoSaleAInternetYDaOk(): void
    {
        $anterior = getenv('ENTORNO');
        putenv('ENTORNO=ci');
        try {
            $r = (new WhatsAppCliente())->enviar('11 1234 5678', 'Ana', 'Aviso de prueba');
            $this->assertTrue($r->ok);

            $invalido = (new WhatsAppCliente())->enviar('123', 'Ana', 'Aviso de prueba');
            $this->assertTrue($invalido->permanente);
            $this->assertSame('TELEFONO_INVALIDO', $invalido->motivo);
        } finally {
            putenv($anterior === false ? 'ENTORNO' : "ENTORNO=$anterior");
        }
    }
}
