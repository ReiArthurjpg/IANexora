<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\AuthProxyService;
use App\Services\DocumentSearchService;
use App\Services\GeminiService;

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

        // Verifica se a mensagem é um comando de autenticação
        $authResult = $this->handleAuthCommand($message);
        if ($authResult !== null) {
            Response::success('Comando de autenticação processado.', $authResult);
            return;
        }

        // Verifica se a mensagem é sobre autenticação
        $authFallback = $this->getAuthIntentFallback($message);
        if ($authFallback !== null) {
            Response::success('Resposta gerada com sucesso.', [
                'message' => $message,
                'context_documents' => [],
                'answer' => $authFallback['answer'],
                'action' => $authFallback['action'],
                'provider' => 'system',
                'error' => null,
            ]);
            return;
        }

        // Fluxo normal: envia para o Gemini com contexto de documentos
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

    private function buildContext(array $documents): string
    {
        $chunks = [];
        foreach ($documents as $document) {
            $chunks[] = "# {$document['title']}\n" . mb_substr((string) $document['content'], 0, 1500);
        }

        return implode("\n\n---\n\n", $chunks);
    }

    /**
     * Detecta se a mensagem do usuário é sobre autenticação e retorna
     * as instruções curtas junto com a action para o front-end exibir o formulário nativo.
     */
    private function getAuthIntentFallback(string $message): ?array
    {
        $msg = mb_strtolower($message);

        $loginKeywords = ['login', 'entrar', 'acessar', 'logar', 'autenticar', 'fazer login'];
        $signupKeywords = ['cadastrar', 'cadastro', 'criar conta', 'registrar', 'registro', 'nova conta', 'signup'];
        $forgotKeywords = ['esqueci', 'recuperar senha', 'forgot', 'redefinir', 'resetar senha', 'perdi minha senha', 'reset'];

        foreach ($loginKeywords as $kw) {
            if (str_contains($msg, $kw)) {
                return [
                    'answer' => "🔐 Aqui está o formulário de login para você acessar sua conta de forma rápida:",
                    'action' => 'show_login_form'
                ];
            }
        }

        foreach ($signupKeywords as $kw) {
            if (str_contains($msg, $kw)) {
                return [
                    'answer' => "📝 Aqui está o formulário para você criar a sua conta na Nexora BJJ:",
                    'action' => 'show_signup_form'
                ];
            }
        }

        foreach ($forgotKeywords as $kw) {
            if (str_contains($msg, $kw)) {
                return [
                    'answer' => "📧 Esqueceu a senha? Sem problemas, preencha o campo abaixo para recuperá-la:",
                    'action' => 'show_forgot_password_form'
                ];
            }
        }

        return null;
    }
}
