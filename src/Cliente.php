<?php

// Un cliente de la veterinaria (clase Cliente del UML).
// A diferencia de Jwt, esta clase es un "molde" para crear objetos: cada objeto representa a una
// persona concreta, con sus propios datos. Por ejemplo: $ana = new Cliente(15, 'Ana', ...);
// Los objetos los crea UsuarioRepositorio a partir de una fila de la tabla usuario.
// Por ahora tiene solo lo que usa la Entrega 3. Los otros métodos del UML (registrarMascota,
// agregarAlCarrito, etc.) se agregan acá cuando lleguen las Entregas 4 y 5.
class Cliente
{
    // El constructor se ejecuta al hacer "new Cliente(...)".
    // Escribir "private" adelante de cada parámetro hace dos cosas a la vez: crea el atributo
    // y le guarda el valor recibido. "private" quiere decir que solo se puede leer desde adentro
    // de la clase; desde afuera se usan los métodos get (getNombre(), getEmail(), ...).
    public function __construct(
        private int $id,
        private string $nombre,
        private string $email,
        private string $passwordHash,
        private string $telefono
    ) {
    }

    // El UML dice iniciarSesion(email, password). El email ya se usó para encontrar a este cliente
    // en la base (UsuarioRepositorio::buscarPorEmail), así que acá solo falta revisar la contraseña.
    // password_verify compara la contraseña escrita con el hash bcrypt guardado.
    // La contraseña nunca se guarda ni se compara "en limpio".
    public function iniciarSesion(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    // El rol no es un atributo del UML (cada clase YA es un rol), pero el JWT y las respuestas lo necesitan.
    public function getRol(): string
    {
        return 'CLIENTE';
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

    // A propósito no hay getPasswordHash(): el hash nunca tiene que salir de la clase
    // (SEGURIDAD.md: las respuestas no incluyen el hash de la contraseña).
}
