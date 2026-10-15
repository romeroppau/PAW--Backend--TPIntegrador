<?php

// Script de carga inicial: crea al veterinario (el administrador del sistema).
// Es la única forma de crear un veterinario: los registros de la web solo crean clientes y rescatistas.
// Se ejecuta desde la terminal, nunca desde el navegador. Los datos se leen de variables de entorno,
// así la contraseña no queda escrita en el repo. Ejemplo de uso (ver más abajo cómo cargar la contraseña):
//   VET_NOMBRE="Dra. López" VET_EMAIL="vet@huellavet.com" php scripts/crear_veterinario.php
// Si el veterinario ya existe, no hace nada: se puede correr varias veces sin duplicarlo.

// 1. Solo desde la terminal. PHP_SAPI dice cómo se está ejecutando PHP: 'cli' es la terminal.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

// 2. Cargar el código común (comun/ de ApiGateway) y las clases de Backend.
//    Igual que en tests/bootstrap.php: COMUN_DIR, o dos carpetas más arriba.
$comun = getenv('COMUN_DIR') ?: __DIR__ . '/../../comun';

if (!is_dir($comun)) {
    echo "No se encontró la carpeta comun/ en: $comun\n";
    exit(1);
}

require_once "$comun/config.php";
require_once "$comun/database.php";
require_once __DIR__ . '/../src/Cliente.php';
require_once __DIR__ . '/../src/Veterinario.php';
require_once __DIR__ . '/../src/Rescatista.php';
require_once __DIR__ . '/../src/UsuarioRepositorio.php';

// 3. Leer los datos. trim saca los espacios del principio y del final.
//    El email se guarda en minúsculas, igual que en los registros.
$nombre = trim((string) getenv('VET_NOMBRE'));
$email = strtolower(trim((string) getenv('VET_EMAIL')));
$password = (string) getenv('VET_PASSWORD');

// 4. Validar con las mismas reglas que el registro (contrato de la API).
$errores = [];

if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
    $errores[] = 'VET_NOMBRE tiene que tener entre 2 y 100 caracteres';
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 150) {
    $errores[] = 'VET_EMAIL no es un email válido';
}

if (mb_strlen($password) < 8 || mb_strlen($password) > 72) {
    $errores[] = 'VET_PASSWORD tiene que tener entre 8 y 72 caracteres';
}

if ($errores !== []) {
    echo implode("\n", $errores) . "\n";
    exit(1);
}

// 5. Si ya existe un usuario con ese email, no se crea de nuevo.
$usuarios = new UsuarioRepositorio(Database::conectar());
$existente = $usuarios->buscarPorEmail($email);

if ($existente instanceof Veterinario) {
    echo "El veterinario $email ya existe. No se hizo nada.\n";
    exit(0);
}

if ($existente !== null) {
    // El email lo tiene un cliente o un rescatista: no se puede usar para el veterinario.
    echo "El email $email ya lo usa otro usuario (rol {$existente->getRol()}). Elegí otro email.\n";
    exit(1);
}

// 6. Crear al veterinario. La contraseña se guarda con bcrypt de costo 12 (SEGURIDAD.md), nunca en limpio.
$id = $usuarios->insertarVeterinario(
    $nombre,
    $email,
    password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
);

echo "Veterinario creado: $email (id $id)\n";
