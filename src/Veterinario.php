<?php

// El veterinario, que es el administrador del sistema (clase Veterinario del UML, sin matrícula).
// No se registra por la web: se crea con el script scripts/crear_veterinario.php.
// Es casi igual a Cliente. No tiene teléfono, y su rol es VETERINARIO.
class Veterinario
{
    public function __construct(
        private int $id,
        private string $nombre,
        private string $email,
        private string $passwordHash
    ) {
    }

    public function iniciarSesion(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function getRol(): string
    {
        return 'VETERINARIO';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
