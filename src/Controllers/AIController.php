<?php

namespace App\Controllers;

use App\AI\AIService;
use Exception;

class AIController {
    /**
     * Procesa las peticiones AJAX de Inteligencia Artificial.
     */
    public function auditarGemini(): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $apiKey = getenv('GEMINI_API_KEY') ?: (getenv('GEMINI_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ($_ENV['GEMINI_KEY'] ?? '')));
            if (empty($apiKey)) {
                throw new Exception("API Key de Gemini no configurada en el entorno.");
            }
            
            require_once dirname(__DIR__) . '/Utils/GeminiAuditor.php';
            $resultados = \App\Utils\GeminiAuditor::auditModels($apiKey);
            
            echo json_encode([
                'success' => true,
                'resultados' => $resultados
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function procesar(): void {
        header('Content-Type: application/json; charset=utf-8');

        $proveedor = $_POST['proveedor'] ?? 'gemini';
        $accionIa = $_POST['accion_ia'] ?? 'chat';
        $prompt = $_POST['prompt'] ?? '';
        $codigo = $_POST['codigo'] ?? '';
        $nombreArchivo = $_POST['nombre_archivo'] ?? 'Sin título';
        $historyJson = $_POST['history'] ?? '[]';
        $history = json_decode($historyJson, true) ?: [];

        try {
            $provider = AIService::getProvider($proveedor);

            $systemInstruction = "Eres un asistente de IA experto en programación y compiladores, integrado directamente en el IDE Indómito. "
                . "Ayudas al usuario a escribir, entender, depurar y optimizar su código. "
                . "El usuario está trabajando actualmente en el archivo: '$nombreArchivo'. "
                . "Responde de forma clara, concisa y usa formato Markdown para resaltar bloques de código. "
                . "No saludes en exceso y ve directo al punto técnico.";

            $opciones = [
                'system_instruction' => $systemInstruction,
                'history' => $history,
                'temperature' => 0.4
            ];

            // Generar el prompt según la acción específica
            switch ($accionIa) {
                case 'explicar':
                    if (empty($codigo)) {
                        throw new Exception("No se ha seleccionado o proporcionado ningún código para explicar.");
                    }
                    $promptFinal = "Por favor, explica detalladamente el funcionamiento y la lógica del siguiente fragmento de código:\n\n```\n$codigo\n```";
                    break;

                case 'corregir':
                    if (empty($codigo)) {
                        throw new Exception("No se ha proporcionado código para analizar errores.");
                    }
                    $promptFinal = "Analiza el siguiente fragmento de código en busca de posibles errores de sintaxis, lógicos, de compilación o malas prácticas, e indica cómo corregirlos:\n\n```\n$codigo\n```";
                    break;

                case 'optimizar':
                    if (empty($codigo)) {
                        throw new Exception("No se ha proporcionado código para optimizar.");
                    }
                    $promptFinal = "Revisa el siguiente código y sugiere optimizaciones de rendimiento, legibilidad o uso de estándares modernos de programación:\n\n```\n$codigo\n```";
                    break;

                case 'chat':
                default:
                    if (empty($prompt)) {
                        throw new Exception("El mensaje de consulta no puede estar vacío.");
                    }
                    // Si se envía código de contexto junto con el chat general
                    if (!empty($codigo)) {
                        $promptFinal = "Con respecto a este código:\n```\n$codigo\n```\n\nPregunta: $prompt";
                    } else {
                        $promptFinal = $prompt;
                    }
                    break;
            }

            $respuesta = $provider->generateResponse($promptFinal, $opciones);

            echo json_encode([
                'success' => true,
                'respuesta' => $respuesta
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Devuelve la lista de proveedores disponibles y si están habilitados (tienen clave configurada).
     */
    public function obtenerProveedores(): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'proveedores' => AIService::getAvailableProviders()
        ]);
        exit;
    }
}
