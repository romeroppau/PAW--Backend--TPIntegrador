<?php

// Valida los datos de los registros de cliente y de rescatista, con las reglas del contrato de la API.
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

        $organizacion = $datos['organizacion'] ?? null;

        if ($organizacion === null || (is_string($organizacion) && trim($organizacion) === '')) {
            $limpios['organizacion'] = null; // vacía o ausente: el rescatista no tiene organización
        } elseif (!is_string($organizacion) || mb_strlen(trim($organizacion)) > 100) {
            $errores['organizacion'] = 'La organización puede tener hasta 100 caracteres';
        } else {
            $limpios['organizacion'] = trim($organizacion);
        }

        return [$limpios, $errores];
    }

    // Los campos que tienen los dos registros: nombre, email, contraseña y teléfono.
    // "&" adelante de $limpios y $errores quiere decir que la función modifica esos arrays
    // directamente (se pasan "por referencia"), en vez de trabajar con una copia.
    private static function validarComunes(array $datos, array &$limpios, array &$errores): void
    {
        // Nombre: obligatorio, de 2 a 100 caracteres. mb_strlen cuenta bien las tildes y la ñ.
        $nombre = self::texto($datos, 'nombre');

        if ($nombre === null || $nombre === '') {
            $errores['nombre'] = 'El nombre es obligatorio';
        } elseif (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            $errores['nombre'] = 'El nombre debe tener entre 2 y 100 caracteres';
        } else {
            $limpios['nombre'] = $nombre;
        }

        // Email: obligatorio, válido y de hasta 150 caracteres. Se guarda en minúsculas.
        $email = self::texto($datos, 'email');

        if ($email === null || $email === '') {
            $errores['email'] = 'El email es obligatorio';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 150) {
            $errores['email'] = 'El email no es válido';
        } else {
            $limpios['email'] = strtolower($email);
        }

        // Contraseña: obligatoria, de 8 a 72 caracteres (72 es el límite de bcrypt).
        // A la contraseña NO se le sacan los espacios: si la persona los puso, son parte de su contraseña.
        $password = $datos['password'] ?? null;

        if ($password === null || $password === '') {
            $errores['password'] = 'La contraseña es obligatoria';
        } elseif (!is_string($password) || mb_strlen($password) < 8 || mb_strlen($password) > 72) {
            $errores['password'] = 'La contraseña debe tener entre 8 y 72 caracteres';
        } else {
            $limpios['password'] = $password;
        }

        // Teléfono: obligatorio y argentino. Se le sacan espacios, guiones y paréntesis.
        // Formato aceptado: 10 dígitos (código de área sin 0 y número sin 15), con +54 y 9 opcionales adelante.
        // Se guardan solo los 10 dígitos, así todos los teléfonos quedan iguales en la base.
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
