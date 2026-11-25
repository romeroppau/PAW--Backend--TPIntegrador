<?php

// Validación de rescatistas (Entrega 5): la veterinaria ve quiénes se registraron y los aprueba o
// los rechaza. Solo un rescatista APROBADO puede publicar en Adopciones.
// Contrato: docs/api/contrato.md (sección "Rescatistas") y contrato-adopciones.md del repo Adopciones.
class RescatistaControlador
{
    private const ESTADOS_FILTRO = ['PENDIENTE', 'APROBADO', 'RECHAZADO', 'TODOS'];

    // GET /api/rescatistas?estado=PENDIENTE&buscar=...&desde=AAAA-MM-DD&hasta=AAAA-MM-DD
    public static function listar(): void
    {
        Auth::requerirRol(['VETERINARIO']);

        // Sin parámetro, los pendientes: es lo que la veterinaria tiene que resolver.
        $estado = $_GET['estado'] ?? 'PENDIENTE';

        if (!is_string($estado) || !in_array($estado, self::ESTADOS_FILTRO, true)) {
            Response::error('El estado tiene que ser PENDIENTE, APROBADO, RECHAZADO o TODOS', 400);
            return;
        }

        [$filtros, $errores] = self::filtros($_GET);

        if ($errores !== []) {
            Response::error('Revisá los filtros', 400, $errores);
            return;
        }

        $rescatistas = (new UsuarioRepositorio(Database::conectar()))->listarRescatistas(
            $estado === 'TODOS' ? null : $estado,
            $filtros['buscar'],
            $filtros['desde'],
            $filtros['hasta']
        );

        Response::json(['rescatistas' => array_map([self::class, 'datos'], $rescatistas)], 200);
    }

    // GET /api/rescatistas/{id} — el perfil del rescatista para la veterinaria (sus publicaciones
    // las da Adopciones: GET /api/adopciones/moderacion?estado=TODAS&rescatista_id={id}).
    public static function ver(array $parametros): void
    {
        Auth::requerirRol(['VETERINARIO']);

        $id = self::idDeRuta($parametros['id'] ?? '');
        $rescatista = $id === null ? null : (new UsuarioRepositorio(Database::conectar()))->buscarRescatista($id);

        if ($rescatista === null) {
            Response::error('Rescatista no encontrado', 404);
            return;
        }

        Response::json(['rescatista' => self::datos($rescatista)], 200);
    }

    // Los filtros de la lista: buscar (hasta 60 letras) y las fechas de validación (desde <= hasta).
    // Devuelve [filtros, errores por campo]. Es pura, para probarla (tests/RescatistaFiltrosTest.php).
    public static function filtros(array $consulta): array
    {
        $filtros = ['buscar' => null, 'desde' => null, 'hasta' => null];
        $errores = [];

        $buscar = $consulta['buscar'] ?? '';
        if (!is_string($buscar) || mb_strlen(trim($buscar)) > 60) {
            $errores['buscar'] = 'La búsqueda puede tener hasta 60 letras';
        } elseif (trim($buscar) !== '') {
            $filtros['buscar'] = trim($buscar);
        }

        foreach (['desde', 'hasta'] as $campo) {
            $valor = $consulta[$campo] ?? '';
            if ($valor === '') {
                continue;
            }
            $dia = is_string($valor) ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;
            if ($dia === false || $dia->format('Y-m-d') !== $valor) {
                $errores[$campo] = 'La fecha no es válida';
                continue;
            }
            $filtros[$campo] = $valor;
        }

        if ($filtros['desde'] !== null && $filtros['hasta'] !== null && $filtros['desde'] > $filtros['hasta']) {
            $errores['hasta'] = 'La fecha "hasta" no puede ser anterior a "desde"';
        }

        return [$filtros, $errores];
    }

    // El motivo del rechazo: obligatorio, de 3 a 255 letras (igual que el de las publicaciones).
    // Devuelve [motivo limpio o null, error o null].
    public static function motivo(mixed $valor): array
    {
        if (!is_string($valor) || trim($valor) === '') {
            return [null, 'Escribí el motivo del rechazo'];
        }

        $motivo = trim($valor);
        $largo = mb_strlen($motivo);

        if ($largo < 3 || $largo > 255) {
            return [null, 'El motivo tiene que tener entre 3 y 255 letras'];
        }

        return [$motivo, null];
    }

    // POST /api/rescatistas/{id}/aprobar
    public static function aprobar(array $parametros): void
    {
        self::validar($parametros, 'APROBADO');
    }

    // POST /api/rescatistas/{id}/rechazar — {"motivo": "..."}
    public static function rechazar(array $parametros): void
    {
        self::validar($parametros, 'RECHAZADO');
    }

    // Aprobar y rechazar hacen lo mismo con otro estado final. Rechazar pide el motivo.
    private static function validar(array $parametros, string $estado): void
    {
        $sesion = Auth::requerirRol(['VETERINARIO']);
        Auth::verificarCsrf($sesion);

        $motivo = null;
        if ($estado === 'RECHAZADO') {
            [$motivo, $error] = self::motivo(Request::json()['motivo'] ?? null);
            if ($error !== null) {
                Response::error('Revisá el motivo del rechazo', 400, ['motivo' => $error]);
                return;
            }
        }

        $id = self::idDeRuta($parametros['id'] ?? '');

        if ($id === null) {
            Response::error('Rescatista no encontrado', 404);
            return;
        }

        $usuarios = new UsuarioRepositorio(Database::conectar());
        $resultado = $usuarios->validarRescatista($id, $estado, $motivo, (int) $sesion['id']);

        if ($resultado === 'NO_EXISTE') {
            Response::error('Rescatista no encontrado', 404);
            return;
        }

        $rescatista = $usuarios->buscarRescatista($id);

        if ($resultado === 'YA_VALIDADO') {
            $palabra = $rescatista->getEstadoValidacion() === 'APROBADO' ? 'aprobado' : 'rechazado';
            Response::error("El rescatista ya fue {$palabra}", 409);
            return;
        }

        // Auditoría: quién validó a quién (SEGURIDAD.md).
        Logger::info("Rescatista {$id} {$estado} por el veterinario {$sesion['id']}");

        self::avisarCuenta($id, $estado, $motivo);

        Response::json(['rescatista' => self::datos($rescatista)], 200);
    }

    // Aviso a la rescatista en la campana (contrato-adopciones.md, sección 8). Lo deja Backend
    // directamente, sin llamada interna. Si falla, la validación ya quedó hecha: solo se anota el error.
    private static function avisarCuenta(int $id, string $estado, ?string $motivo): void
    {
        try {
            (new NotificacionRepositorio(Database::conectar()))->insertar(
                $id,
                'CUENTA',
                'WEB',
                self::mensajeDeCuenta($estado, $motivo),
                date('Y-m-d H:i:s'),
                "cuenta-{$id}-{$estado}"
            );
        } catch (Throwable $error) {
            Logger::error("No se pudo dejar el aviso de cuenta al rescatista {$id}: " . $error->getMessage());
        }
    }

    public static function mensajeDeCuenta(string $estado, ?string $motivo = null): string
    {
        if ($estado === 'APROBADO') {
            return '¡Tu cuenta de rescatista fue aprobada! Ya podés publicar a los animales que tenés a cargo en Mis publicaciones.';
        }

        $porque = $motivo !== null ? " Motivo: {$motivo}." : '';

        return 'La veterinaria no aprobó tu cuenta de rescatista, así que no vas a poder publicar.'
            . $porque . ' Si creés que es un error, escribinos.';
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
            'estado_validacion' => $rescatista->getEstadoValidacion(),
            // Cuándo lo aprobó o rechazó la veterinaria ("AAAA-MM-DD HH:MM:SS", hora de Argentina) y,
            // si lo rechazó, por qué. null si sigue pendiente o se validó antes de guardarse (022).
            'validado_en' => $rescatista->getValidadoEn(),
            'motivo_rechazo' => $rescatista->getMotivoRechazo()
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
