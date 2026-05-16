<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Throwable;

class GeminiService implements AIService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => rtrim($_ENV['GEMINI_BASE_URL'] ?? 'https://generativelanguage.googleapis.com', '/'),
            'timeout' => 30,
        ]);
    }

    public function sendMessage(string $message, string $context): array
    {
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        $model = $_ENV['GEMINI_MODEL'] ?? 'gemini-2.5-flash';
        $systemPrompt = $_ENV['GEMINI_SYSTEM_PROMPT'] ?? 'Você é um assistente útil.';

        if ($apiKey === '') {
            return ['provider' => 'gemini', 'answer' => 'Gemini não configurado. Defina GEMINI_API_KEY no .env.'];
        }

        $prompt = "Contexto disponível:\n{$context}\n\nPergunta do usuário:\n{$message}";

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $response = $this->client->post($url, [
                'json' => [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => $systemPrompt]
                        ]
                    ],
                    'contents' => [[
                        'parts' => [[
                            'text' => $prompt,
                        ]],
                    ]],
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            $answer = $payload['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta do modelo.';

            return ['provider' => 'gemini', 'answer' => $answer, 'raw' => $payload];
        } catch (Throwable $exception) {
            return ['provider' => 'gemini', 'answer' => 'Falha na comunicação com Gemini.', 'error' => $exception->getMessage()];
        }
    }
}
