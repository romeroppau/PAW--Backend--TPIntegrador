<?php

use PHPUnit\Framework\TestCase;

// El objeto de sesión que devuelven el login, los registros y /yo (contrato.md).
final class SesionTest extends TestCase
{
    public function testElRescatistaLlevaSuEstadoDeValidacion(): void
    {
        $rescatista = new Rescatista(7, 'Juan Gómez', 'juan@mail.com', 'hash', '2323444444', null, 'PENDIENTE');

        $datos = Sesion::datos($rescatista, 'abc');

        $this->assertSame('RESCATISTA', $datos['rol']);
        $this->assertSame('PENDIENTE', $datos['estado_validacion']);
        $this->assertSame('abc', $datos['csrf']);
        $this->assertArrayNotHasKey('password_hash', $datos);
    }

    public function testLosDemasRolesNoTienenEstadoDeValidacion(): void
    {
        $cliente = new Cliente(3, 'Ana Pérez', 'ana@mail.com', 'hash', '2323555555');
        $veterinario = new Veterinario(1, 'Vet', 'vet@mail.com', 'hash');

        $this->assertNull(Sesion::datos($cliente, 'abc')['estado_validacion']);
        $this->assertNull(Sesion::datos($veterinario, 'abc')['estado_validacion']);
    }
}
