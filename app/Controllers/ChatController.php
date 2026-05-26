<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\AuthProxyService;
use App\Services\ChatHistoryService;
use App\Services\GeminiService;
use GuzzleHttp\Client;
use Throwable;

class ChatController
{
    /**
     * Padrões de comandos de autenticação suportados no chat
     */
    private const AUTH_PATTERNS = [
        'login'     => '/^LOGIN:\s*(.+?)\s*\|\s*(.+)$/i',
        'signup'    => '/^CADASTRO:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i',
        'forgot'    => '/^RECUPERAR:\s*(.+)$/i',
        'reset'     => '/^REDEFINIR:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i',
    ];

    public function chat(): void
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        $message = trim((string) ($payload['message'] ?? ''));

        if ($message === '') {
            Response::error('Mensagem é obrigatória.', ['message' => 'Informe um texto para consulta.'], 422);
            return;
        }

        $sessionId = $payload['session_id'] ?? 'default_session';
        $historyService = new ChatHistoryService();
        $history = $historyService->getHistory($sessionId);

        // Verifica se a mensagem é um comando explícito de autenticação
        $authResult = $this->handleAuthCommand($message);
        if ($authResult !== null) {
            Response::success('Comando de autenticação processado.', $authResult);
            return;
        }

        // Buscar contexto de documentos na API do BrainNexora
        $context = $this->fetchContextFromBrain($message);

        // Ajusta a mensagem enviada para o modelo, forçando respostas naturais e focadas
        $enhancedPrompt = "Aja de forma totalmente humana, simpática e natural, como se estivesse conversando comigo. "
            . "Sua função é me ajudar respondendo à minha pergunta, mas você DEVE basear sua resposta ESTRITAMENTE nos documentos de contexto fornecidos abaixo. "
            . "Se a resposta não estiver clara no contexto, não invente dados; diga gentilmente que não possui essa informação. "
            . "\n\nMinha pergunta: " . $message;

        // Envia para o Gemini passando a mensagem enriquecida e o contexto de documentos
        $aiResult = (new GeminiService())->sendMessage($enhancedPrompt, $context, $history);

        // Salva no histórico original (apenas a mensagem do usuário e a resposta, sem o prompt extra)
        if (!empty($aiResult['answer']) && ($aiResult['answer'] !== 'Falha na comunicação com Gemini.')) {
            $historyService->save($sessionId, 'user', $message);
            $historyService->save($sessionId, 'model', $aiResult['answer']);
        }

        Response::success('Resposta gerada com sucesso.', [
            'message' => $message,
            'context_documents' => [],
            'answer' => $aiResult['answer'] ?? 'Sem resposta.',
            'provider' => $aiResult['provider'] ?? 'gemini',
            'error' => $aiResult['error'] ?? null,
        ]);
    }

    /**
     * Busca os documentos mais relevantes na API do BrainNexora usando a mensagem do usuário.
     */
    private function fetchContextFromBrain(string $message): string
    {
        $brainUrl = rtrim($_ENV['BRAIN_API_URL'] ?? 'http://brainnexora-web', '/');
        
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get($brainUrl . '/api/search', [
                'query' => [
                    'query' => $message,
                    'limit' => 3
                ]
            ]);

            $data = json_decode((string) $response->getBody(), true);
            
            if (!empty($data['success']) && !empty($data['data']['results'])) {
                $contextParts = [];
                foreach ($data['data']['results'] as $result) {
                    $contextParts[] = "--- Título do Documento: {$result['title']} ---\n" . $result['content'];
                }
                return implode("\n\n", $contextParts);
            }
        } catch (Throwable $e) {
            // Em caso de falha na comunicação com o BrainNexora, apenas retorna contexto vazio
            return "Falha ao buscar contexto: " . $e->getMessage();
        }

        return '';
    }

    /**
     * Detecta e executa comandos de autenticação.
     * Retorna null se a mensagem não for um comando.
     */
    private function handleAuthCommand(string $message): ?array
    {
        $auth = new AuthProxyService();

        // LOGIN: email | senha
        if (preg_match(self::AUTH_PATTERNS['login'], $message, $m)) {
            $result = $auth->login(trim($m[1]), trim($m[2]));
            return $this->formatAuthResponse('login', $result);
        }

        // CADASTRO: nome | email | academia | senha | confirmar_senha
        if (preg_match(self::AUTH_PATTERNS['signup'], $message, $m)) {
            $result = $auth->signup(trim($m[1]), trim($m[2]), trim($m[3]), trim($m[4]), trim($m[5]));
            return $this->formatAuthResponse('signup', $result);
        }

        // RECUPERAR: email
        if (preg_match(self::AUTH_PATTERNS['forgot'], $message, $m)) {
            $result = $auth->forgotPassword(trim($m[1]));
            return $this->formatAuthResponse('forgot', $result);
        }

        // REDEFINIR: token | nova_senha | confirmar_senha
        if (preg_match(self::AUTH_PATTERNS['reset'], $message, $m)) {
            $result = $auth->resetPassword(trim($m[1]), trim($m[2]), trim($m[3]));
            return $this->formatAuthResponse('reset', $result);
        }

        return null;
    }

    /**
     * Formata a resposta da autenticação para exibir no chat.
     */
    private function formatAuthResponse(string $action, array $result): array
    {
        if ($result['success']) {
            $answer = match ($action) {
                'login' => $this->formatLoginSuccess($result['data']),
                'signup' => $this->formatSignupSuccess($result['data']),
                'forgot' => "📧 " . ($result['data']['message'] ?? 'Solicitação enviada! Verifique seu e-mail para o link de recuperação.'),
                'reset' => "🔑 " . ($result['data']['message'] ?? 'Senha redefinida com sucesso! Você já pode fazer login com a nova senha.'),
                default => 'Ação realizada com sucesso.',
            };
        } else {
            $data = $result['data'];
            $errorMsg = $data['error']['message'] ?? $data['message'] ?? 'Erro desconhecido.';
            $details = $data['error']['details'] ?? $data['details'] ?? [];

            $answer = "❌ **Erro**: {$errorMsg}";
            if (!empty($details)) {
                foreach ($details as $field => $msgs) {
                    $msgs = is_array($msgs) ? implode(', ', $msgs) : $msgs;
                    $answer .= "\n  • **{$field}**: {$msgs}";
                }
            }
        }

        return [
            'message' => '[Comando de Autenticação]',
            'action' => $action,
            'answer' => $answer,
            'provider' => 'auth_proxy',
            'auth_data' => $result['success'] ? $result['data'] : null,
            'error' => $result['success'] ? null : $result['data'],
        ];
    }

    private function formatLoginSuccess(array $data): string
    {
        $userName = $data['user']['name'] ?? 'Usuário';
        $token = $data['accessToken'] ?? '';
        $expiresIn = $data['expiresIn'] ?? 3600;
        $minutes = intdiv($expiresIn, 60);

        return "✅ **Login realizado com sucesso!**\n\n"
            . "👤 Bem-vindo(a), **{$userName}**!\n"
            . "🔐 Seu token JWT foi gerado e expira em {$minutes} minutos.\n\n"
            . "```\nToken: {$token}\n```";
    }

    private function formatSignupSuccess(array $data): string
    {
        $userName = $data['user']['name'] ?? 'Usuário';
        $userEmail = $data['user']['email'] ?? '';

        return "✅ **Conta criada com sucesso!**\n\n"
            . "👤 Nome: **{$userName}**\n"
            . "📧 E-mail: **{$userEmail}**\n\n"
            . "Agora você pode fazer login usando o comando:\n"
            . "`LOGIN: {$userEmail} | sua_senha`";
    }
}
