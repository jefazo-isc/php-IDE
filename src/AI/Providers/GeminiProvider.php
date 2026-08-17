<?php

namespace App\AI\Providers;

use App\AI\AIProviderInterface;
use App\Utils\RateLimiter;
use Exception;

class GeminiProvider implements AIProviderInterface {
    private string $apiKey;
    
    private string $modelName;
    private RateLimiter $rateLimiter;

    public function __construct(string $modelName = 'gemini-1.5-flash') {
        // Obtenemos la clave de API desde el entorno
        $this->apiKey = getenv('GEMINI_API_KEY') ?: (getenv('GEMINI_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ($_ENV['GEMINI_KEY'] ?? '')));
        if (empty($this->apiKey)) {
            throw new Exception("API Key de Gemini no encontrada. Por favor configúrala en el archivo .env como GEMINI_API_KEY.");
        }

        if (strpos($modelName, 'models/') !== 0 && $modelName !== 'gemini') {
            $this->modelName = "models/" . $modelName;
        } elseif ($modelName === 'gemini') {
            $this->modelName = "models/gemini-1.5-flash";
        } else {
            $this->modelName = $modelName;
        }

        $this->rateLimiter = new RateLimiter(14, 60.0, 'gemini_api');
    }

    public function generateResponse(string $prompt, array $options = []): string {
        $this->rateLimiter->wait();
        $url = "https://generativelanguage.googleapis.com/v1beta/{$this->modelName}:generateContent?key={$this->apiKey}";

        // Construir la estructura de contents compatible con Gemini
        $contents = [];
        
        // Si hay historial, lo procesamos
        if (!empty($options['history'])) {
            foreach ($options['history'] as $msg) {
                // Mapeo simple de roles de 'user' / 'assistant' (que Gemini llama 'user' / 'model')
                $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]]
                ];
            }
        }
        
        // Añadir la pregunta actual
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];

        $payload = ['contents' => $contents];

        // Añadir instrucción de sistema si existe
        if (!empty($options['system_instruction'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $options['system_instruction']]]
            ];
        }

        // Configuración de generación
        if (!empty($options['temperature'])) {
            $payload['generationConfig']['temperature'] = (float)$options['temperature'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("Error de conexión cURL con Gemini: " . $error_msg);
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            $errData = json_decode($response, true);
            $msg = $errData['error']['message'] ?? 'Error desconocido';
            throw new Exception("Error de la API de Gemini (Código HTTP {$httpCode}): {$msg}");
        }

        $result = json_decode($response, true);
        
        // Extraer texto de la respuesta
        if (!empty($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }

        return "Error: No se recibió respuesta válida del modelo.";
    }
}
