<?php

// Un rescatista: publica animales en adopción (clase Rescatista del UML).
// Se registra en estado PENDIENTE y el veterinario lo aprueba o lo rechaza (eso es de la Entrega 5).
// Puede iniciar sesión en cualquier estado, pero solo publica si está APROBADO.
class Rescatista
{
    // "?string" quiere decir que puede ser texto o null: la organización es opcional.
    public function __construct(
        private int $id,
        private string $nombre,
        private string $email,
        private string $passwordHash,
        private string $telefono,
        private ?string $organizacion,
        private string $estadoValidacion
    ) {
    }

    public function iniciarSesion(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    // Del UML: mientras no esté APROBADO, no puede publicar en adopciones.
    // El JWT no lleva el estado (solo id, rol y csrf), así que en la Entrega 5 Adopciones
    // va a leer el estado desde la base para usar esto.
    public function puedePublicar(): bool
    {
        return $this->estadoValidacion === 'APROBADO';
    }

    public function getRol(): string
    {
        return 'RESCATISTA';
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

    public function getTelefono(): string
    {
        return $this->telefono;
    }

    public function getOrganizacion(): ?string
    {
        return $this->organizacion;
    }

    public function getEstadoValidacion(): string
    {
        return $this->estadoValidacion;
    }
}
