<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\DocumentSearchService;
use App\Services\GeminiService;

class ChatController
{
    public function chat(): void
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        $message = trim((string) ($payload['message'] ?? ''));

        if ($message === '') {
            Response::error('Mensagem é obrigatória.', ['message' => 'Informe um texto para consulta.'], 422);
            return;
        }

        $sessionId = $payload['session_id'] ?? 'default_session';

        $historyService = new \App\Services\ChatHistoryService();
        $history = $historyService->getHistory($sessionId);

        $documents = DocumentSearchService::make()->search($message, 5);
        $context = $this->buildContext($documents);
        $aiResult = (new GeminiService())->sendMessage($message, $context, $history);

        // Salva no histórico se a resposta for válida
        if (!empty($aiResult['answer']) && ($aiResult['answer'] !== 'Falha na comunicação com Gemini.')) {
            $historyService->save($sessionId, 'user', $message);
            $historyService->save($sessionId, 'model', $aiResult['answer']);
        }

        Response::success('Resposta gerada com sucesso.', [
            'message' => $message,
            'context_documents' => array_column($documents, 'id'),
            'answer' => $aiResult['answer'] ?? 'Sem resposta.',
            'provider' => $aiResult['provider'] ?? 'gemini',
            'error' => $aiResult['error'] ?? null,
        ]);
    }

    private function buildContext(array $documents): string
    {
        $chunks = [];
        foreach ($documents as $document) {
            $chunks[] = "# {$document['title']}\n" . mb_substr((string) $document['content'], 0, 1500);
        }

        return implode("\n\n---\n\n", $chunks);
    }
}
