<?php

namespace App\AI\Providers;

use App\AI\AIProviderInterface;
use Exception;

class GrokProvider implements AIProviderInterface {
    private string $apiKey;
    private string $modelName = 'grok-2';

    public function __construct() {
        $this->apiKey = getenv('GROK_API_KEY') ?: (getenv('GROK_KEY') ?: (getenv('XAI_API_KEY') ?: ($_ENV['GROK_API_KEY'] ?? ($_ENV['GROK_KEY'] ?? ($_ENV['XAI_API_KEY'] ?? '')))));
        if (empty($this->apiKey)) {
            throw new Exception("API Key de Grok (xAI) no encontrada. Por favor configúrala en el archivo .env como GROK_API_KEY.");
        }
    }

    public function generateResponse(string $prompt, array $options = []): string {
        $url = "https://api.x.ai/v1/chat/completions";

        $messages = [];

        // Añadir instrucción de sistema si existe
        if (!empty($options['system_instruction'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['system_instruction']
            ];
        }

        // Si hay historial, lo procesamos
        if (!empty($options['history'])) {
            foreach ($options['history'] as $msg) {
                $messages[] = [
                    'role' => $msg['role'], // 'user' o 'assistant'
                    'content' => $msg['content']
                ];
            }
        }

        // Pregunta actual
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        $payload = [
            'model' => $this->modelName,
            'messages' => $messages
        ];

        if (!empty($options['temperature'])) {
            $payload['temperature'] = (float)$options['temperature'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer {$this->apiKey}"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error de conexión cURL con Grok: " . $error_msg);
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            $errData = json_decode($response, true);
            $msg = $errData['error']['message'] ?? 'Error desconocido';
            throw new Exception("Error de la API de Grok (Código HTTP {$httpCode}): {$msg}");
        }

        $result = json_decode($response, true);

        if (!empty($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }

        return "Error: No se recibió respuesta válida de Grok.";
    }
}
