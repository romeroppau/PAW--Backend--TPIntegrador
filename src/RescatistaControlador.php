<?php

// Validación de rescatistas (Entrega 5): la veterinaria ve quiénes se registraron y los aprueba o
// los rechaza. Solo un rescatista APROBADO puede publicar en Adopciones.
// Contrato: docs/api/contrato.md (sección "Rescatistas") y contrato-adopciones.md del repo Adopciones.
class RescatistaControlador
{
    private const ESTADOS_FILTRO = ['PENDIENTE', 'APROBADO', 'RECHAZADO', 'TODOS'];

    // GET /api/rescatistas?estado=PENDIENTE
    public static function listar(): void
    {
        Auth::requerirRol(['VETERINARIO']);

        // Sin parámetro, los pendientes: es lo que la veterinaria tiene que resolver.
        $estado = $_GET['estado'] ?? 'PENDIENTE';

        if (!is_string($estado) || !in_array($estado, self::ESTADOS_FILTRO, true)) {
            Response::error('El estado tiene que ser PENDIENTE, APROBADO, RECHAZADO o TODOS', 400);
            return;
        }

        $rescatistas = (new UsuarioRepositorio(Database::conectar()))
            ->listarRescatistas($estado === 'TODOS' ? null : $estado);

        Response::json(['rescatistas' => array_map([self::class, 'datos'], $rescatistas)], 200);
    }

    // POST /api/rescatistas/{id}/aprobar
    public static function aprobar(array $parametros): void
    {
        self::validar($parametros, 'APROBADO');
    }

    // POST /api/rescatistas/{id}/rechazar
    public static function rechazar(array $parametros): void
    {
        self::validar($parametros, 'RECHAZADO');
    }

    // Aprobar y rechazar hacen lo mismo con otro estado final.
    private static function validar(array $parametros, string $estado): void
    {
        $sesion = Auth::requerirRol(['VETERINARIO']);
        Auth::verificarCsrf($sesion);

        $id = self::idDeRuta($parametros['id'] ?? '');

        if ($id === null) {
            Response::error('Rescatista no encontrado', 404);
            return;
        }

        $usuarios = new UsuarioRepositorio(Database::conectar());
        $resultado = $usuarios->validarRescatista($id, $estado);

        if ($resultado === 'NO_EXISTE') {
            Response::error('Rescatista no encontrado', 404);
            return;
        }

        $rescatista = $usuarios->buscarPorId($id);

        if ($resultado === 'YA_VALIDADO') {
            $palabra = $rescatista->getEstadoValidacion() === 'APROBADO' ? 'aprobado' : 'rechazado';
            Response::error("El rescatista ya fue {$palabra}", 409);
            return;
        }

        // Auditoría: quién validó a quién (SEGURIDAD.md).
        Logger::info("Rescatista {$id} {$estado} por el veterinario {$sesion['id']}");

        Response::json(['rescatista' => self::datos($rescatista)], 200);
    }

    // Lo que se muestra de un rescatista (nunca el hash de la contraseña).
    private static function datos(Rescatista $rescatista): array
    {
        return [
            'id' => $rescatista->getId(),
            'nombre' => $rescatista->getNombre(),
            'email' => $rescatista->getEmail(),
            'telefono' => $rescatista->getTelefono(),
            'organizacion' => $rescatista->getOrganizacion(),
            'estado_validacion' => $rescatista->getEstadoValidacion()
        ];
    }

    // El {id} de la ruta: solo dígitos y mayor que 0. Cualquier otra cosa es un rescatista que no existe.
    private static function idDeRuta(string $valor): ?int
    {
        if (!ctype_digit($valor) || strlen($valor) > 10 || (int) $valor < 1) {
            return null;
        }

        return (int) $valor;
    }
}
