<?php

// Valida los datos de los registros de cliente y de rescatista, con las reglas del contrato de la API.
// También "Mis datos" (cambiar nombre, teléfono y organización) y el cambio de contraseña, con las
// mismas reglas y los mismos mensajes que el registro.
// Devuelve dos cosas: los datos ya limpios (sin espacios de más, email en minúsculas, teléfono normalizado)
// y los errores por campo, que van en "campos" de la respuesta 400.
// Se valida en el servidor aunque el formulario también valide: cualquiera puede mandar el pedido
// sin usar el formulario (SEGURIDAD.md).
class ValidadorRegistro
{
    // Valida los datos de un cliente. Devuelve [datos limpios, errores].
    // Si no hay errores, el array de errores está vacío.
    public static function cliente(array $datos): array
    {
        $limpios = [];
        $errores = [];

        self::validarComunes($datos, $limpios, $errores);

        return [$limpios, $errores];
    }

    // Igual que el cliente, más la organización (opcional).
    public static function rescatista(array $datos): array
    {
        $limpios = [];
        $errores = [];

        self::validarComunes($datos, $limpios, $errores);
        self::validarOrganizacion($datos, $limpios, $errores);

        return [$limpios, $errores];
    }

    // "Mis datos": lo que cada uno puede cambiar de su cuenta. El email no se cambia (es el usuario
    // para entrar), así que si viene se ignora.
    // - Cliente y rescatista: nombre y teléfono, obligatorios como en el registro.
    // - Rescatista: además la organización (opcional).
    // - Veterinaria: nombre (obligatorio) y teléfono (opcional: se creó sin teléfono).
    // Devuelve [datos limpios, errores]; en limpios, telefono es null si la veterinaria lo dejó vacío.
    public static function cuenta(array $datos, string $rol): array
    {
        $limpios = [];
        $errores = [];

        self::validarNombre($datos, $limpios, $errores);

        $telefono = self::texto($datos, 'telefono');
        if ($rol === 'VETERINARIO' && ($telefono === null || $telefono === '')) {
            $limpios['telefono'] = null;
        } else {
            self::validarTelefono($datos, $limpios, $errores);
        }

        if ($rol === 'RESCATISTA') {
            self::validarOrganizacion($datos, $limpios, $errores);
        }

        return [$limpios, $errores];
    }

    // Cambio de contraseña: la actual (se compara después con la guardada), la nueva con las mismas
    // reglas que el registro, y la nueva repetida. Devuelve [datos limpios, errores].
    public static function cambioDeContrasena(array $datos): array
    {
        $limpios = [];
        $errores = [];

        $actual = $datos['actual'] ?? null;
        if (!is_string($actual) || $actual === '') {
            $errores['actual'] = 'Escribí tu contraseña actual';
        } else {
            $limpios['actual'] = $actual;
        }

        self::validarPassword($datos, 'nueva', $limpios, $errores);

        $repetir = $datos['repetir'] ?? null;
        if (!isset($errores['nueva']) && $repetir !== ($limpios['nueva'] ?? null)) {
            $errores['repetir'] = 'Las contraseñas nuevas no coinciden';
        }
        if (!isset($errores['nueva']) && isset($limpios['actual']) && $limpios['nueva'] === $limpios['actual']) {
            $errores['nueva'] = 'La contraseña nueva tiene que ser distinta de la actual';
        }

        return [$limpios, $errores];
    }

    // Los campos que tienen los dos registros: nombre, email, contraseña y teléfono.
    // "&" adelante de $limpios y $errores quiere decir que la función modifica esos arrays
    // directamente (se pasan "por referencia"), en vez de trabajar con una copia.
    private static function validarComunes(array $datos, array &$limpios, array &$errores): void
    {
        self::validarNombre($datos, $limpios, $errores);

        // Email: obligatorio, válido y de hasta 150 caracteres. Se guarda en minúsculas.
        $email = self::texto($datos, 'email');

        if ($email === null || $email === '') {
            $errores['email'] = 'El email es obligatorio';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 150) {
            $errores['email'] = 'El email no es válido';
        } else {
            $limpios['email'] = strtolower($email);
        }

        self::validarPassword($datos, 'password', $limpios, $errores);
        self::validarTelefono($datos, $limpios, $errores);
    }

    // Nombre: obligatorio, de 2 a 100 caracteres. mb_strlen cuenta bien las tildes y la ñ.
    private static function validarNombre(array $datos, array &$limpios, array &$errores): void
    {
        $nombre = self::texto($datos, 'nombre');

        if ($nombre === null || $nombre === '') {
            $errores['nombre'] = 'El nombre es obligatorio';
        } elseif (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            $errores['nombre'] = 'El nombre debe tener entre 2 y 100 caracteres';
        } else {
            $limpios['nombre'] = $nombre;
        }
    }

    // Contraseña ($campo: "password" en el registro, "nueva" al cambiarla): obligatoria, de 8 a 72
    // caracteres (72 es el límite de bcrypt). No se le sacan los espacios: si la persona los puso,
    // son parte de su contraseña.
    private static function validarPassword(array $datos, string $campo, array &$limpios, array &$errores): void
    {
        $password = $datos[$campo] ?? null;

        if ($password === null || $password === '') {
            $errores[$campo] = 'La contraseña es obligatoria';
        } elseif (!is_string($password) || mb_strlen($password) < 8 || mb_strlen($password) > 72) {
            $errores[$campo] = 'La contraseña debe tener entre 8 y 72 caracteres';
        } else {
            $limpios[$campo] = $password;
        }
    }

    // Teléfono: obligatorio y argentino. Se le sacan espacios, guiones y paréntesis.
    // Formato aceptado: 10 dígitos (código de área sin 0 y número sin 15), con +54 y 9 opcionales adelante.
    // Se guardan solo los 10 dígitos, así todos los teléfonos quedan iguales en la base.
    private static function validarTelefono(array $datos, array &$limpios, array &$errores): void
    {
        $telefono = self::texto($datos, 'telefono');

        if ($telefono === null || $telefono === '') {
            $errores['telefono'] = 'El teléfono es obligatorio';
        } else {
            $soloDigitos = preg_replace('/[\s\-()]/', '', $telefono);

            if (preg_match('/^(\+?54)?9?(\d{10})$/', $soloDigitos, $partes) === 1) {
                $limpios['telefono'] = $partes[2]; // los 10 dígitos
            } else {
                $errores['telefono'] = 'El teléfono no es válido. Ejemplo: 2323 555555';
            }
        }
    }

    // Organización del rescatista: opcional, hasta 100 caracteres. Vacía o ausente: null.
    private static function validarOrganizacion(array $datos, array &$limpios, array &$errores): void
    {
        $organizacion = $datos['organizacion'] ?? null;

        if ($organizacion === null || (is_string($organizacion) && trim($organizacion) === '')) {
            $limpios['organizacion'] = null; // vacía o ausente: el rescatista no tiene organización
        } elseif (!is_string($organizacion) || mb_strlen(trim($organizacion)) > 100) {
            $errores['organizacion'] = 'La organización puede tener hasta 100 caracteres';
        } else {
            $limpios['organizacion'] = trim($organizacion);
        }
    }

    // Lee un campo de texto y le saca los espacios del principio y del final.
    // Devuelve null si el campo no vino; si vino algo que no es texto (un número, una lista),
    // devuelve '' para que se marque como inválido.
    private static function texto(array $datos, string $campo): ?string
    {
        if (!array_key_exists($campo, $datos) || $datos[$campo] === null) {
            return null;
        }

        return is_string($datos[$campo]) ? trim($datos[$campo]) : '';
    }
}
