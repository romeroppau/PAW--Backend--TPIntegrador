<?php

// Envía un aviso por WhatsApp con la plantilla aprobada (Meta Cloud API).
// En ENTORNO local o ci no sale a internet: simula el envío.
class WhatsAppCliente
{
    private const TIMEOUT_SEGUNDOS = 8;

    public static function simulado(): bool
    {
        return in_array(getenv('ENTORNO') ?: '', ['local', 'ci'], true);
    }

    /** Normaliza a formato internacional sin '+' (Argentina móvil: 549 + 10 dígitos). */
    public static function normalizarTelefono(string $tel): ?string
    {
        $d = preg_replace('/\D+/', '', $tel);
        if (preg_match('/^549\d{10}$/', $d))
            return $d;
        if (preg_match('/^54\d{10}$/', $d))
            return '549' . substr($d, 2);
        if (preg_match('/^\d{10}$/', $d))
            return '549' . $d;
        return null;
    }

    /** true si se envió; false si falló (el motivo va al log, sin datos sensibles). */
    public function enviar(string $telefono, string $nombre, string $mensaje): bool
    {
        $destino = self::normalizarTelefono($telefono);
        if ($destino === null) {
            Logger::error('WhatsApp: teléfono con formato inválido');
            return false;
        }

        if (self::simulado()) {
            return true;
        }

        $token = getenv('WHATSAPP_TOKEN') ?: '';
        $numeroId = getenv('WHATSAPP_NUMERO_ID') ?: '';
        if ($token === '' || $numeroId === '') {
            Logger::error('WhatsApp: falta ' . ($token === '' ? 'WHATSAPP_TOKEN ' : '') . ($numeroId === '' ? 'WHATSAPP_NUMERO_ID' : ''));
            return false;
        }

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

        $ch = curl_init("https://graph.facebook.com/v20.0/{$numeroId}/messages");
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
        curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($codigo < 200 || $codigo >= 300) {
            Logger::error("WhatsApp: la API respondió $codigo");   // nunca el token ni el teléfono
            return false;
        }
        return true;
    }
}