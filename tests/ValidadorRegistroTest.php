<?php

use PHPUnit\Framework\TestCase;

// Pruebas del ValidadorRegistro: las reglas de los datos del registro (contrato de la API).
// No usan la base de datos: el validador recibe un array y devuelve [datos limpios, errores].
final class ValidadorRegistroTest extends TestCase
{
    // Grupo 1: con todos los datos bien, no hay errores y los datos salen limpios.
    public function testDatosCorrectosDeUnCliente(): void
    {
        [$limpios, $errores] = ValidadorRegistro::cliente([
            'nombre' => '  Ana Pérez  ',
            'email' => '  Ana@Mail.COM ',
            'password' => 'unaClave123',
            'telefono' => '2323 555555'
        ]);

        // assertSame([], ...): comprueba que la lista de errores esté vacía.
        $this->assertSame([], $errores);

        // Los datos limpios: sin espacios de más, email en minúsculas, teléfono solo con los 10 dígitos.
        $this->assertSame('Ana Pérez', $limpios['nombre']);
        $this->assertSame('ana@mail.com', $limpios['email']);
        $this->assertSame('unaClave123', $limpios['password']);
        $this->assertSame('2323555555', $limpios['telefono']);
    }

        // Grupo 2: los teléfonos válidos, escritos de distintas formas, quedan guardados como 10 dígitos.
    // Así todos los teléfonos quedan iguales en la base.
    public function testLosTelefonosValidosQuedanEnDiezDigitos(): void
    {
        $formas = [
            '2323555555',          // solo los 10 dígitos
            '2323 555555',         // con espacio
            '2323-555555',         // con guion
            '(2323) 55-5555',      // con paréntesis
            '+54 9 2323 555555',   // con código de país y el 9 del celular
            '5492323555555'        // lo mismo, todo junto
        ];

        // foreach recorre la lista: prueba cada forma, una por una.
        foreach ($formas as $telefono) {
            [$limpios, $errores] = ValidadorRegistro::cliente($this->datosCliente(['telefono' => $telefono]));

            // El tercer parámetro es el mensaje que muestra PHPUnit si falla: así sabés cuál de las formas falló.
            $this->assertSame([], $errores, "El teléfono '$telefono' tendría que ser válido");
            $this->assertSame('2323555555', $limpios['telefono'], "El teléfono '$telefono' tendría que quedar en 10 dígitos");
        }
    }

    // Los teléfonos que no son argentinos o están incompletos dan error.
    // Según el contrato, el código de área va sin el 0 y el número sin el 15.
    public function testLosTelefonosInvalidosDanError(): void
    {
        $invalidos = [
            '123',               // muy corto
            '23235555551',       // 11 dígitos
            'abcdefghij',        // letras
            '+1 555 123 4567',   // de otro país
            '011 15 5555 5555'   // con el 0 y el 15 (no se aceptan)
        ];

        foreach ($invalidos as $telefono) {
            [, $errores] = ValidadorRegistro::cliente($this->datosCliente(['telefono' => $telefono]));

            $this->assertSame(
                'El teléfono no es válido. Ejemplo: 2323 555555',
                $errores['telefono'] ?? null,
                "El teléfono '$telefono' tendría que dar error"
            );
        }
    }

        // Grupo 3: si falta un campo (o viene vacío o con solo espacios), el mensaje es "... es obligatorio".
    public function testLosCamposFaltantesSonObligatorios(): void
    {
        // Sin ningún campo.
        [, $errores] = ValidadorRegistro::cliente([]);

        $this->assertSame('El nombre es obligatorio', $errores['nombre']);
        $this->assertSame('El email es obligatorio', $errores['email']);
        $this->assertSame('La contraseña es obligatoria', $errores['password']);
        $this->assertSame('El teléfono es obligatorio', $errores['telefono']);

        // Con los campos vacíos o con solo espacios: también cuentan como faltantes.
        [, $errores] = ValidadorRegistro::cliente([
            'nombre' => '   ',
            'email' => '',
            'password' => '',
            'telefono' => '  '
        ]);

        $this->assertCount(4, $errores); // assertCount: comprueba cuántos elementos tiene la lista
        $this->assertSame('El nombre es obligatorio', $errores['nombre']);
    }

        // Grupo 4: valores que vienen pero no cumplen las reglas del contrato.
    public function testLosValoresInvalidosDanError(): void
    {
        // Email mal escrito.
        foreach (['ana@', 'ana.mail.com', 'ana @mail.com'] as $email) {
            [, $errores] = ValidadorRegistro::cliente($this->datosCliente(['email' => $email]));
            $this->assertSame('El email no es válido', $errores['email'] ?? null, "El email '$email' tendría que dar error");
        }

        // Nombre de 1 letra, y de 101 letras (el máximo es 100).
        // str_repeat('a', 101) arma un texto con 101 letras "a".
        foreach (['A', str_repeat('a', 101)] as $nombre) {
            [, $errores] = ValidadorRegistro::cliente($this->datosCliente(['nombre' => $nombre]));
            $this->assertSame('El nombre debe tener entre 2 y 100 caracteres', $errores['nombre'] ?? null);
        }

        // Contraseña de 7 y de 73 caracteres (tiene que tener entre 8 y 72).
        foreach ([str_repeat('a', 7), str_repeat('a', 73)] as $password) {
            [, $errores] = ValidadorRegistro::cliente($this->datosCliente(['password' => $password]));
            $this->assertSame('La contraseña debe tener entre 8 y 72 caracteres', $errores['password'] ?? null);
        }
    }

    // Los límites justos SÍ son válidos: nombre de 2 y de 100 letras, contraseña de 8 y de 72 caracteres.
    // Los errores de programación suelen estar justo en los bordes (un < donde iba un <=).
    public function testLosLimitesJustosSonValidos(): void
    {
        foreach ([2, 100] as $largo) {
            [, $errores] = ValidadorRegistro::cliente($this->datosCliente(['nombre' => str_repeat('a', $largo)]));
            $this->assertSame([], $errores, "Un nombre de $largo letras tendría que ser válido");
        }

        foreach ([8, 72] as $largo) {
            [, $errores] = ValidadorRegistro::cliente($this->datosCliente(['password' => str_repeat('a', $largo)]));
            $this->assertSame([], $errores, "Una contraseña de $largo caracteres tendría que ser válida");
        }
    }

        // Grupo 5: a la contraseña NO se le sacan los espacios (son parte de la contraseña que eligió la persona).
    public function testLaContrasenaNoSeRecorta(): void
    {
        [$limpios, $errores] = ValidadorRegistro::cliente($this->datosCliente(['password' => '  clave con espacios  ']));

        $this->assertSame([], $errores);
        $this->assertSame('  clave con espacios  ', $limpios['password']);
    }

    // Si alguien manda a mano un campo que no es texto (una lista, un número, true), el validador
    // lo marca como error y sigue: no se rompe.
    public function testLosCamposQueNoSonTextoDanErrorSinRomper(): void
    {
        [, $errores] = ValidadorRegistro::cliente([
            'nombre' => ['a', 'b'],
            'email' => 123,
            'password' => ['clave'],
            'telefono' => true
        ]);

        // assertArrayHasKey: comprueba que el array tenga esa clave (o sea, que haya un error en ese campo).
        $this->assertArrayHasKey('nombre', $errores);
        $this->assertArrayHasKey('email', $errores);
        $this->assertArrayHasKey('password', $errores);
        $this->assertArrayHasKey('telefono', $errores);
    }

        // Grupo 6: la organización del rescatista es opcional.
    // Si no viene, o viene vacía o con solo espacios, queda como "sin organización" (null).
    public function testRescatistaSinOrganizacion(): void
    {
        foreach ([$this->datosCliente(), $this->datosCliente(['organizacion' => '   '])] as $datos) {
            [$limpios, $errores] = ValidadorRegistro::rescatista($datos);

            $this->assertSame([], $errores);
            $this->assertNull($limpios['organizacion']);
        }
    }

    // Con organización: se le sacan los espacios. Hasta 100 caracteres es válida; con 101, error.
    public function testRescatistaConOrganizacion(): void
    {
        [$limpios, $errores] = ValidadorRegistro::rescatista($this->datosCliente(['organizacion' => '  Patitas Luján  ']));
        $this->assertSame([], $errores);
        $this->assertSame('Patitas Luján', $limpios['organizacion']);

        [, $errores] = ValidadorRegistro::rescatista($this->datosCliente(['organizacion' => str_repeat('a', 100)]));
        $this->assertSame([], $errores);

        [, $errores] = ValidadorRegistro::rescatista($this->datosCliente(['organizacion' => str_repeat('a', 101)]));
        $this->assertSame('La organización puede tener hasta 100 caracteres', $errores['organizacion'] ?? null);
    }


    // Datos de un cliente, todos válidos. Se puede cambiar uno o más campos, por ejemplo:
    // $this->datosCliente(['telefono' => '123']).
    // array_merge junta los dos arrays: si un campo está en los dos, gana el de $cambios.
    private function datosCliente(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Ana Pérez',
            'email' => 'ana@mail.com',
            'password' => 'unaClave123',
            'telefono' => '2323555555'
        ], $cambios);
    }

}
