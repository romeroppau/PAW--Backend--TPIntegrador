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

    public function testLaTiendaTieneSusPlazosConfigurables(): void
    {
        [$limpios, $errores] = CatalogoConfiguracion::validar(['minutos_para_pagar' => 20, 'dias_habiles_para_retirar' => '7', 'hora_avisos_tienda' => '10:30'], []);

        $this->assertSame([], $errores);
        $this->assertSame(['minutos_para_pagar' => '20', 'dias_habiles_para_retirar' => '7', 'hora_avisos_tienda' => '10:30'], $limpios);
    }

    public function testElPrimerRecordatorioTieneQueSerAntesDelUltimo(): void
    {
        // Con 5 días para retirar, el último recordatorio es el día 4: el primero puede ser hasta el 3.
        [, $errores] = CatalogoConfiguracion::validar(['dia_primer_recordatorio' => 4], []);
        $this->assertArrayHasKey('dia_primer_recordatorio', $errores);

        [, $sinErrores] = CatalogoConfiguracion::validar(['dia_primer_recordatorio' => 3], []);
        $this->assertSame([], $sinErrores);

        // Achicar el plazo por debajo del primer recordatorio guardado: el error va en el plazo.
        [, $errores] = CatalogoConfiguracion::validar(['dias_habiles_para_retirar' => 3], ['dia_primer_recordatorio' => '2']);
        $this->assertArrayHasKey('dias_habiles_para_retirar', $errores);
    }

    public function testElReintegroPorCancelarTieneSusValoresPorDefecto(): void
    {
        $porClave = array_column(CatalogoConfiguracion::listar([]), 'valor', 'clave');

        $this->assertSame(75, $porClave['porcentaje_reintegro_anticipado']);
        $this->assertSame(50, $porClave['porcentaje_reintegro_ultimo_momento']);
        $this->assertSame(48, $porClave['horas_anticipacion_reintegro']);
    }

    public function testElReintegroAUltimoMomentoNoPuedeSerMayorQueConAnticipacion(): void
    {
        [, $errores] = CatalogoConfiguracion::validar(['porcentaje_reintegro_ultimo_momento' => 80], []);
        $this->assertArrayHasKey('porcentaje_reintegro_ultimo_momento', $errores);

        // Iguales sí (y 0 % también: sin reintegro).
        [, $sinErrores] = CatalogoConfiguracion::validar(['porcentaje_reintegro_ultimo_momento' => 75], []);
        $this->assertSame([], $sinErrores);
        [, $sinErrores] = CatalogoConfiguracion::validar(['porcentaje_reintegro_anticipado' => 0, 'porcentaje_reintegro_ultimo_momento' => 0], []);
        $this->assertSame([], $sinErrores);

        // Bajar el de anticipación por debajo del guardado a último momento: el error va en el que se cambió.
        [, $errores] = CatalogoConfiguracion::validar(['porcentaje_reintegro_anticipado' => 40], ['porcentaje_reintegro_ultimo_momento' => '50']);
        $this->assertArrayHasKey('porcentaje_reintegro_anticipado', $errores);

        [, $errores] = CatalogoConfiguracion::validar(['porcentaje_reintegro_anticipado' => 101, 'horas_anticipacion_reintegro' => 0], []);
        $this->assertArrayHasKey('porcentaje_reintegro_anticipado', $errores);
        $this->assertArrayHasKey('horas_anticipacion_reintegro', $errores);
    }

    // El precio de la consulta (Pacientes, promoción de turnos): pesos enteros, de 0 a 1.000.000.
    public function testElPrecioDeLaConsultaEsEnPesosEnteros(): void
    {
        $this->assertSame('15000', CatalogoConfiguracion::defecto('precio_consulta'));   // como texto, igual que se guarda

        [$limpios, $errores] = CatalogoConfiguracion::validar(['precio_consulta' => 18500], []);
        $this->assertSame([], $errores);
        $this->assertSame(['precio_consulta' => '18500'], $limpios);

        [, $errores] = CatalogoConfiguracion::validar(['precio_consulta' => 1000001], []);
        $this->assertArrayHasKey('precio_consulta', $errores);
        [, $errores] = CatalogoConfiguracion::validar(['precio_consulta' => '15000.50'], []);
        $this->assertArrayHasKey('precio_consulta', $errores);
    }

    public function testLosDatosDeContactoTienenSusValoresPorDefecto(): void
    {
        $contacto = CatalogoConfiguracion::contacto([]);

        $this->assertSame('Av. San Martín 1450, Luján', $contacto['direccion']);
        $this->assertSame('11 3901-0486', $contacto['telefono']);
        $this->assertNull($contacto['email']);
        $this->assertNull($contacto['instagram']);   // sin cargar: la página no muestra el ícono
        $this->assertSame(['telefono' => 'tel:+541139010486', 'whatsapp' => 'https://wa.me/5491139010486'], $contacto['enlaces']);
    }

    public function testLoGuardadoDeContactoSeUsaYArmaLosEnlaces(): void
    {
        $guardado = fn ($valor) => ['valor' => $valor, 'actualizado_en' => '2026-11-23 10:00:00', 'actualizado_por' => 'Vet'];
        $contacto = CatalogoConfiguracion::contacto([
            'whatsapp' => $guardado('+54 9 2323 555-555'),
            'instagram' => $guardado('https://www.instagram.com/huellavet'),
        ]);

        $this->assertSame('+54 9 2323 555-555', $contacto['whatsapp']);   // se muestra como se escribió
        $this->assertSame('https://wa.me/5492323555555', $contacto['enlaces']['whatsapp']);
        $this->assertSame('https://www.instagram.com/huellavet', $contacto['instagram']);
    }

    public function testLosDatosDeContactoValidos(): void
    {
        [$limpios, $errores] = CatalogoConfiguracion::validar([
            'direccion' => '  Calle   Falsa 123, Luján ',
            'telefono' => '(2323) 42-1234',
            'email_contacto' => 'Hola@HuellaVet.com.ar',
            'facebook' => 'https://m.facebook.com/huellavet',
            'youtube' => '',
        ], []);

        $this->assertSame([], $errores);
        $this->assertSame('Calle Falsa 123, Luján', $limpios['direccion']);
        $this->assertSame('(2323) 42-1234', $limpios['telefono']);
        $this->assertSame('hola@huellavet.com.ar', $limpios['email_contacto']);
        $this->assertSame('https://m.facebook.com/huellavet', $limpios['facebook']);
        $this->assertSame('', $limpios['youtube']);   // vacío vale: es el valor por defecto
    }

    public function testCadaDatoDeContactoMalTieneSuError(): void
    {
        [, $errores] = CatalogoConfiguracion::validar([
            'direccion' => '',
            'telefono' => '123',
            'whatsapp' => 1139010486,
            'email_contacto' => 'no-es-un-email',
            'instagram' => 'https://www.facebook.com/huellavet',
            'facebook' => 'http://www.facebook.com/huellavet',
            'youtube' => 'https://youtube.com.otro.com/x',
        ], []);

        $this->assertSame(['direccion', 'telefono', 'whatsapp', 'email_contacto', 'instagram', 'facebook', 'youtube'], array_keys($errores));
        $this->assertStringStartsWith('El teléfono no es válido. Ejemplo: 11 3901-0486', $errores['telefono']);
        $this->assertStringContainsString('instagram.com', $errores['instagram']);
    }

    public function testElWhatsAppPuedeSerDeOtroPaisConSuCodigo(): void
    {
        // El número de prueba de Meta: de EE. UU., con + y el 1.
        [$limpios, $errores] = CatalogoConfiguracion::validar(['whatsapp' => '+1 (555) 145-9000', 'telefono' => '+1 555 145 9000'], []);
        $this->assertSame([], $errores);
        $this->assertSame('+1 (555) 145-9000', $limpios['whatsapp']);   // se muestra como se escribió

        $guardado = fn ($valor) => ['valor' => $valor, 'actualizado_en' => '2026-11-23 10:00:00', 'actualizado_por' => 'Vet'];
        $contacto = CatalogoConfiguracion::contacto(['whatsapp' => $guardado('+1 (555) 145-9000'), 'telefono' => $guardado('+1 555 145 9000')]);
        $this->assertSame('https://wa.me/15551459000', $contacto['enlaces']['whatsapp']);
        $this->assertSame('tel:+15551459000', $contacto['enlaces']['telefono']);
    }

    public function testUnNumeroDeOtroPaisTieneQueTenerElMasYUnLargoPosible(): void
    {
        foreach (['15551459000', '+1 555', '+0 555 145 9000', '+1 555 145 9000 1234 56'] as $malo) {
            [, $errores] = CatalogoConfiguracion::validar(['whatsapp' => $malo], []);
            $this->assertArrayHasKey('whatsapp', $errores, $malo);
        }
        // Argentino con +54 sigue siendo argentino: WhatsApp le agrega el 9.
        $contacto = CatalogoConfiguracion::contacto(['whatsapp' => ['valor' => '+54 11 3901-0486', 'actualizado_en' => 'x', 'actualizado_por' => 'Vet']]);
        $this->assertSame('https://wa.me/5491139010486', $contacto['enlaces']['whatsapp']);
    }

    public function testSeAtiendeLas24Horas(): void
    {
        [$limpios, $errores] = CatalogoConfiguracion::validar(['hora_apertura' => '00:00', 'hora_cierre' => '23:59'], []);

        $this->assertSame([], $errores);
        $this->assertSame(['hora_apertura' => '00:00', 'hora_cierre' => '23:59'], $limpios);
    }

    public function testHorarioCortadoEnDosFranjas(): void
    {
        // De 9 a 13 y de 16 a 19.
        [$limpios, $errores] = CatalogoConfiguracion::validar(
            ['hora_apertura' => '09:00', 'hora_cierre' => '13:00', 'hora_apertura_2' => '16:00', 'hora_cierre_2' => '19:00'],
            []
        );
        $this->assertSame([], $errores);
        $this->assertSame('16:00', $limpios['hora_apertura_2']);

        // Sin cargar, la segunda franja queda vacía (de corrido, como siempre).
        $porClave = array_column(CatalogoConfiguracion::listar([]), 'valor', 'clave');
        $this->assertSame('', $porClave['hora_apertura_2']);

        // Vaciarla vuelve a atender de corrido.
        [$limpios, $errores] = CatalogoConfiguracion::validar(['hora_apertura_2' => '', 'hora_cierre_2' => ''], ['hora_apertura_2' => '16:00', 'hora_cierre_2' => '19:00']);
        $this->assertSame([], $errores);
        $this->assertSame(['hora_apertura_2' => '', 'hora_cierre_2' => ''], $limpios);
    }

    public function testLaSegundaFranjaVaCompletaYDespuesDeLaPrimera(): void
    {
        [, $errores] = CatalogoConfiguracion::validar(['hora_apertura_2' => '16:00'], []);
        $this->assertSame(['hora_cierre_2'], array_keys($errores));   // falta el cierre

        // El primer horario es hasta las 20 (por defecto): a las 16 todavía está abierto.
        [, $errores] = CatalogoConfiguracion::validar(['hora_apertura_2' => '16:00', 'hora_cierre_2' => '19:00'], []);
        $this->assertSame(['hora_apertura_2'], array_keys($errores));

        [, $errores] = CatalogoConfiguracion::validar(
            ['hora_cierre' => '13:00', 'hora_apertura_2' => '19:00', 'hora_cierre_2' => '16:00'],
            []
        );
        $this->assertSame(['hora_cierre_2'], array_keys($errores));

        // Alargar el primero hasta pisar el segundo guardado: el error va en el que se cambió.
        [, $errores] = CatalogoConfiguracion::validar(['hora_cierre' => '17:00'], ['hora_cierre' => '13:00', 'hora_apertura_2' => '16:00', 'hora_cierre_2' => '19:00']);
        $this->assertSame(['hora_cierre'], array_keys($errores));
    }
}
