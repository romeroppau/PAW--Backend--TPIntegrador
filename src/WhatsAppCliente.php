<?php

// Envía un aviso por WhatsApp con la plantilla aprobada (Meta Cloud API).
// En ENTORNO local o ci no sale a internet: simula el envío.
//
// enviar() devuelve un ResultadoWhatsApp:
//   - ok:          Meta aceptó el mensaje.
//   - permanente:  no tiene sentido reintentar (teléfono inválido, número no verificado en la
//                  cuenta de prueba, plantilla mal configurada, token vencido). El aviso queda solo
//                  en la web y se anota el motivo.
//   - transitorio: timeout, 5xx, límite de mensajes. La próxima tarea lo reintenta.
//   - configuracion: falta una variable, token vencido, plantilla mal. Fallarían todos: la tarea
//                  corta sin gastar intentos.
class WhatsAppCliente
{
    private const TIMEOUT_SEGUNDOS = 8;
    // v20.0 venció el 24/09/2026 (Meta la redirige sola a la más vieja vigente). Se puede cambiar
    // sin tocar el código con WHATSAPP_API_VERSION.
    private const VERSION_API = 'v25.0';

    // Códigos de error de Meta (error.code) que no se arreglan reintentando ESE aviso: son del
    // destinatario o del texto.
    // https://developers.facebook.com/documentation/business-messaging/whatsapp/support/error-codes
    private const ERRORES_PERMANENTES = [
        100,     // parámetro inválido (p. ej. número de teléfono mal formado)
        131009,  // valor de parámetro inválido
        131026,  // el destinatario no tiene WhatsApp / no puede recibir el mensaje
        131030,  // el número no está en la lista de destinatarios verificados (número de prueba)
        131051,  // tipo de mensaje no soportado
        132005,  // el texto con los parámetros es demasiado largo
        132007,  // el texto viola una política de la plantilla
        132012,  // formato de parámetro inválido
    ];

    // Errores de la cuenta o de la plantilla: fallarían todos los avisos. La tarea corta ahí, sin
    // gastar intentos, y los avisos salen cuando se arregla la configuración.
    private const ERRORES_CONFIGURACION = [
        190,     // token vencido o inválido
        10,      // permiso denegado
        200,     // permiso denegado
        132000,  // cantidad de parámetros distinta a la de la plantilla
        132001,  // la plantilla no existe en ese idioma
        132015,  // plantilla pausada
        132016,  // plantilla deshabilitada
    ];

    public static function simulado(): bool
    {
        return in_array(getenv('ENTORNO') ?: '', ['local', 'ci'], true);
    }

    /**
     * Normaliza a formato internacional sin '+' (Argentina móvil: 549 + 10 dígitos).
     * Acepta las formas en que la gente anota el celular: +54 9 11 1234-5678, 011 15 1234-5678,
     * 0351 15 123-4567, 11 1234 5678, etc.
     */
    public static function normalizarTelefono(string $tel): ?string
    {
        $d = preg_replace('/\D+/', '', $tel);

        if (str_starts_with($d, '00')) {          // 0054 9 ...
            $d = substr($d, 2);
        }
        if (preg_match('/^549\d{10}$/', $d)) {
            return $d;
        }
        if (preg_match('/^54(\d{10})$/', $d, $m)) {
            return '549' . $m[1];
        }
        if (preg_match('/^54(\d{12})$/', $d, $m)) {  // 54 + característica + 15 + número
            $sin15 = self::quitar15($m[1]);
            return $sin15 === null ? null : '549' . $sin15;
        }

        if (str_starts_with($d, '0')) {           // 011..., 0351...
            $d = substr($d, 1);
        }
        if (preg_match('/^\d{10}$/', $d)) {
            return '549' . $d;
        }
        if (preg_match('/^\d{12}$/', $d)) {
            $sin15 = self::quitar15($d);
            return $sin15 === null ? null : '549' . $sin15;
        }
        return null;
    }

    /**
     * Argentina: la cuenta de prueba de Meta a veces tiene registrado el número sin el 9
     * (54 + 10 dígitos) y rechaza el 549... con 131030. Esta es la otra forma para reintentar.
     */
    public static function formaAlternativa(string $normalizado): ?string
    {
        if (preg_match('/^549(\d{10})$/', $normalizado, $m)) {
            return '54' . $m[1];
        }
        return null;
    }

    public static function esPermanente(int $codigoMeta): bool
    {
        return in_array($codigoMeta, self::ERRORES_PERMANENTES, true);
    }

    public static function esDeConfiguracion(int $codigoMeta): bool
    {
        return in_array($codigoMeta, self::ERRORES_CONFIGURACION, true);
    }

    // 12 dígitos = característica (2 a 4) + 15 + número: se saca el 15.
    private static function quitar15(string $d): ?string
    {
        $posiciones = str_starts_with($d, '11') ? [2] : [3, 4, 2];
        foreach ($posiciones as $p) {
            if (substr($d, $p, 2) === '15') {
                return substr($d, 0, $p) . substr($d, $p + 2);
            }
        }
        return null;
    }

    public function enviar(string $telefono, string $nombre, string $mensaje): ResultadoWhatsApp
    {
        $destino = self::normalizarTelefono($telefono);
        if ($destino === null) {
            Logger::error('WhatsApp: teléfono con formato inválido');
            return ResultadoWhatsApp::permanente('TELEFONO_INVALIDO');
        }

        if (self::simulado()) {
            return ResultadoWhatsApp::ok();
        }

        $token = getenv('WHATSAPP_TOKEN') ?: '';
        // WHATSAPP_NUMERO_ID es el nombre del plan; se acepta también WHATSAPP_NUMBER_ID.
        $numeroId = getenv('WHATSAPP_NUMERO_ID') ?: (getenv('WHATSAPP_NUMBER_ID') ?: '');
        if ($token === '' || $numeroId === '') {
            Logger::error('WhatsApp: falta ' . ($token === '' ? 'WHATSAPP_TOKEN ' : '') . ($numeroId === '' ? 'WHATSAPP_NUMERO_ID' : ''));
            return ResultadoWhatsApp::configuracion('SIN_CONFIGURACION');
        }

        $resultado = $this->llamarApi($token, $numeroId, $destino, $nombre, $mensaje);

        // Número de prueba + celular argentino: probar sin el 9.
        if ($resultado->codigoMeta === 131030 && ($alt = self::formaAlternativa($destino)) !== null) {
            $resultado = $this->llamarApi($token, $numeroId, $alt, $nombre, $mensaje);
        }

        return $resultado;
    }

    private function llamarApi(string $token, string $numeroId, string $destino, string $nombre, string $mensaje): ResultadoWhatsApp
    {
        $cuerpo = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $destino,
            'type' => 'template',
            'template' => [
                'name' => getenv('WHATSAPP_PLANTILLA') ?: 'aviso_huellavet',
                'language' => ['code' => getenv('WHATSAPP_IDIOMA') ?: 'es_AR'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $nombre],
                            ['type' => 'text', 'text' => $mensaje],
                        ],
                    ]
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $version = getenv('WHATSAPP_API_VERSION') ?: self::VERSION_API;
        $ch = curl_init("https://graph.facebook.com/{$version}/{$numeroId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SEGUNDOS,
        ]);
        $respuesta = curl_exec($ch);
        $errorCurl = curl_errno($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errorCurl !== 0 || $respuesta === false) {
            Logger::error("WhatsApp: no se pudo conectar con Meta (curl $errorCurl)");
            return ResultadoWhatsApp::transitorio('SIN_CONEXION');
        }

        if ($codigo >= 200 && $codigo < 300) {
            return ResultadoWhatsApp::ok();
        }

        return self::interpretarError($codigo, (string) $respuesta);
    }

    /** Arma el resultado a partir de la respuesta de error de Meta (sin token ni teléfono en el log). */
    public static function interpretarError(int $codigoHttp, string $respuesta): ResultadoWhatsApp
    {
        $json = json_decode($respuesta, true);
        $codigoMeta = is_array($json) && isset($json['error']['code']) ? (int) $json['error']['code'] : 0;

        Logger::error("WhatsApp: la API respondió $codigoHttp (error de Meta $codigoMeta)");

        $motivo = "META_{$codigoMeta}_HTTP_{$codigoHttp}";
        if (self::esDeConfiguracion($codigoMeta) || $codigoHttp === 401) {
            return ResultadoWhatsApp::configuracion($motivo, $codigoMeta);
        }
        if ($codigoMeta !== 0 && self::esPermanente($codigoMeta)) {
            return ResultadoWhatsApp::permanente($motivo, $codigoMeta);
        }
        // 4xx sin código conocido: error del pedido, no se arregla solo.
        if ($codigoMeta === 0 && $codigoHttp >= 400 && $codigoHttp < 500 && $codigoHttp !== 429) {
            return ResultadoWhatsApp::permanente($motivo);
        }
        return ResultadoWhatsApp::transitorio($motivo, $codigoMeta);
    }
}

final class ResultadoWhatsApp
{
    private function __construct(
        public readonly bool $ok,
        public readonly bool $permanente,
        public readonly bool $deConfiguracion,
        public readonly string $motivo,
        public readonly int $codigoMeta,
    ) {}

    public static function ok(): self
    {
        return new self(true, false, false, '', 0);
    }

    public static function permanente(string $motivo, int $codigoMeta = 0): self
    {
        return new self(false, true, false, $motivo, $codigoMeta);
    }

    public static function transitorio(string $motivo, int $codigoMeta = 0): self
    {
        return new self(false, false, false, $motivo, $codigoMeta);
    }

    public static function configuracion(string $motivo, int $codigoMeta = 0): self
    {
        return new self(false, false, true, $motivo, $codigoMeta);
    }
}
