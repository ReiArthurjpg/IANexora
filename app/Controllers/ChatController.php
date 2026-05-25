<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\AuthProxyService;
use App\Services\ChatHistoryService;
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

        $sessionId = $payload['session_id'] ?? 'default_session';
        $historyService = new ChatHistoryService();
        $history = $historyService->getHistory($sessionId);

        // Verifica se a mensagem é um comando de autenticação
        $authResult = $this->handleAuthCommand($message);
        if ($authResult !== null) {
            Response::success('Comando de autenticação processado.', $authResult);
            return;
        }

        // Verifica se a mensagem é sobre autenticação (passando histórico)
        $authFallback = $this->getAuthIntentFallback($message, $history);
        if ($authFallback !== null) {
            // Salva a interação no histórico para que possamos monitorar o contexto na próxima mensagem
            $historyService->save($sessionId, 'user', $message);
            $historyService->save($sessionId, 'model', $authFallback['answer']);

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

        // Fluxo normal: envia para o Gemini sem contexto local de documentos
        $aiResult = (new GeminiService())->sendMessage($message, '', $history);

        // Salva no histórico se a resposta for válida
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

    /**
     * Detecta se a mensagem do usuário é sobre autenticação e retorna
     * a resposta curta com a action para o front-end exibir o formulário nativo.
     * Também detecta se o usuário optou pelo caminho da página padrão e devolve
     * um guia de passo a passo em vez do formulário nativo.
     */
    private function getAuthIntentFallback(string $message, array $history = []): ?array {

        $msg = mb_strtolower($message);

        // 1.1 Detecção de Intenção Explícita para abrir o formulário de recuperação de senha no chat
        if (preg_match('/(abrir|abra|mandar|manda|mande|quero|enviar|envie|mostrar|mostra|esqueci|esqueceu).*formul[aá]rio.*(recuper|redefin|reset|senha)/iu', $msg)
            || preg_match('/(recuperar|redefinir|resetar).*(senha).*(no|pelo|por).*(chat|caht)/iu', $msg)
            || preg_match('/(formulario|formulário) (de )?(recupera[cç][aã]o|redefini[cç][aã]o|reset).*(senha)?/iu', $msg)) {
            return [
                'answer' => "📩 Aqui está o formulário para envio do link de recuperação de senha:",
                'action' => 'show_forgot_password_form',
            ];
        }

        // 1.2 Detecção de Intenção Explícita para abrir o formulário de cadastro no chat
        if (preg_match('/(abrir|abra|mandar|manda|mande|quero|enviar|envie|mostrar|mostra|cade|cadê).*formul[aá]rio.*cadastr/iu', $msg) || 
            preg_match('/cadastro (no|pelo|por) (chat|caht)/iu', $msg) ||
            preg_match('/cadastrar (no|pelo|por) (chat|caht)/iu', $msg) ||
            preg_match('/(formulario|formulário) (de )?cadastro/iu', $msg)) {
            
            return [
                'answer' => "📝 Aqui está o formulário de cadastro para você criar sua conta de forma rápida:",
                'action' => 'show_signup_form',
            ];
        }

        // 1. Detecção de Intenção Explícita para abrir o formulário de login no chat
        if (preg_match('/(abrir|abra|mandar|manda|mande|quero|enviar|envie|mostrar|mostra|cade|cadê).*formul[aá]rio/iu', $msg) || 
            preg_match('/login (no|pelo|por) (chat|caht)/iu', $msg) ||
            preg_match('/entrar (no|pelo|por) (chat|caht)/iu', $msg) ||
            preg_match('/(formulario|formulário) (de )?login/iu', $msg)) {
            
            return [
                'answer' => "🔐 Aqui está o formulário de login para você acessar sua conta de forma rápida:",
                'action' => 'show_login_form',
            ];
        }

        

        // 1.5 Detecção de Intenção Geral de Login para bypassar Gemini e exibir a oferta inicial
        $isGeneralLogin = false;
        $generalPatterns = [
            '/fazer\s+(o\s+)?login/iu',
            '/realiza[cç][ãa]o\s+de\s+login/iu',
            '/realizar\s+login/iu',
            '/como\s+(fazer\s+)?login/iu',
            '/como\s+(eu\s+)?entrar/iu',
            '/como\s+logar/iu',
            '/quero\s+logar/iu',
            '/onde\s+(fa[cç]o|fica|fazer)\s+login/iu',
            '/onde\s+(fa[cç]o|fica|fazer)\s+o\s+bot[ãa]o/iu',
            '/entrar\s+(na\s+)?conta/iu',
            '/acessar\s+(a\s+)?conta/iu',
            '/tela\s+de\s+login/iu',
            '/p[aá]gina\s+de\s+login/iu',
            '/bot[ãa]o\s+de\s+login/iu',
            '/^login$/iu',
            '/^entrar$/iu',
            '/^logar$/iu',
        ];

        foreach ($generalPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                $isGeneralLogin = true;
                break;
            }
        }

        if ($isGeneralLogin) {
            return [
                'answer' => "Você pode acessar a nossa **[Página de Login](/guest/login)** padrão ou, se preferir, posso abrir um formulário interativo diretamente aqui no chat para você entrar rapidamente. Deseja fazer o login por aqui pelo chat?",
                'action' => null,
            ];
        }

        // 1.7 Detecção de Intenção Geral de Cadastro para bypassar Gemini e exibir a oferta inicial
        $isGeneralSignup = false;
        $generalSignupPatterns = [
            '/fazer\s+(o\s+)?cadastro/iu',
            '/realiza[cç][ãa]o\s+de\s+cadastro/iu',
            '/realizar\s+cadastro/iu',
            '/como\s+(fazer\s+)?cadastro/iu',
            '/como\s+(eu\s+)?cadastrar/iu',
            '/como\s+criar\s+(uma\s+)?conta/iu',
            '/criar\s+(uma\s+)?conta/iu',
            '/onde\s+(fa[cç]o|fica|fazer)\s+cadastro/iu',
            '/onde\s+(fa[cç]o|fica|fazer)\s+o\s+registro/iu',
            '/registrar\s+(minha\s+)?conta/iu',
            '/novo\s+cadastro/iu',
            '/p[aá]gina\s+de\s+cadastro/iu',
            '/tela\s+de\s+cadastro/iu',
            '/^cadastro$/iu',
            '/^cadastrar$/iu',
            '/^registrar$/iu',
        ];

        foreach ($generalSignupPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                $isGeneralSignup = true;
                break;
            }
        }

        if ($isGeneralSignup) {
            return [
                'answer' => "Você pode acessar a nossa **[Página de Cadastro](/guest/login/signup)** padrão ou, se preferir, posso abrir um formulário de cadastro interativo diretamente aqui no chat para você criar sua conta rapidamente. Deseja realizar o cadastro por aqui pelo chat?",
                'action' => null,
            ];
        }

        // 1.8 Detecção de Intenção Geral de recuperação de senha para bypassar Gemini e exibir a oferta inicial
        $isGeneralForgot = false;
        $generalForgotPatterns = [
            '/esqueci\s+(a\s+)?senha/iu',
            '/esqueci\s+minha\s+senha/iu',
            '/recuperar\s+(a\s+)?senha/iu',
            '/redefinir\s+(a\s+)?senha/iu',
            '/reset(ar)?\s+(a\s+)?senha/iu',
            '/n[aã]o\s+consigo\s+entrar/iu',
            '/n[aã]o\s+lembro\s+(da\s+)?senha/iu',
            '/link\s+de\s+recupera[cç][aã]o/iu',
            '/p[aá]gina\s+de\s+(recupera[cç][aã]o|redefini[cç][aã]o)\s+de\s+senha/iu',
            '/^recuperar senha$/iu',
            '/^redefinir senha$/iu',
        ];

        foreach ($generalForgotPatterns as $pattern) {
            if (preg_match($pattern, $msg)) {
                $isGeneralForgot = true;
                break;
            }
        }

        if ($isGeneralForgot) {
            return [
                'answer' => "Você pode acessar a nossa **[Página de Recuperação de Senha](/guest/forgot-password)** padrão ou, se preferir, posso abrir um formulário interativo diretamente aqui no chat para enviar o link de recuperação. Deseja recuperar a senha por aqui pelo chat?",
                'action' => null,
            ];
        }

        // Verifica se a IA tinha acabado de oferecer o formulário de login ou cadastro no chat
        $lastModelMsg = '';
        if (!empty($history)) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                if ($history[$i]['role'] === 'model') {
                    $lastModelMsg = mb_strtolower($history[$i]['content']);
                    break;
                }
            }
        }

        // Detect if the previous model message offered the signup form
        $aiOfferedSignupChat = $lastModelMsg !== '' && (
            str_contains($lastModelMsg, 'cadastro por aqui')
            || str_contains($lastModelMsg, 'formulário de cadastro')
            || str_contains($lastModelMsg, 'formulario de cadastro')
            || str_contains($lastModelMsg, 'cadastro pelo chat')
            || str_contains($lastModelMsg, 'criar sua conta rapidamente')
            || str_contains($lastModelMsg, 'deseja realizar o cadastro por aqui')
            || str_contains($lastModelMsg, 'cadastrar')
            || str_contains($lastModelMsg, 'registro')
        );
        // Detect if the previous model message offered the login form (only if signup not offered)
        $aiOfferedLoginChat = $lastModelMsg !== '' && !$aiOfferedSignupChat && (
            str_contains($lastModelMsg, 'login por aqui')
            || str_contains($lastModelMsg, 'formulário de login')
            || str_contains($lastModelMsg, 'formulario de login')
            || str_contains($lastModelMsg, 'entrar rapidamente')
            || str_contains($lastModelMsg, 'login pelo chat')
            || str_contains($lastModelMsg, 'entrar por aqui')
            || str_contains($lastModelMsg, 'deseja fazer o login por aqui')
        );
        $aiOfferedForgotChat = $lastModelMsg !== '' && (
            str_contains($lastModelMsg, 'recuperação de senha')
            || str_contains($lastModelMsg, 'recuperacao de senha')
            || str_contains($lastModelMsg, 'recuperar a senha por aqui')
            || str_contains($lastModelMsg, 'link de recuperação')
            || str_contains($lastModelMsg, 'redefinir senha')
            || str_contains($lastModelMsg, 'esqueci a senha')
        );


        // Detect when user asks explanation/flow of the page instead of chat form
        $isAskingHowPageWorks = preg_match('/(como\s+funciona|me\s+explica|explicar|passo\s+a\s+passo|onde\s+fica|qual\s+o\s+caminho).*(p[aá]gina|pagina|tela|site|bot[ãa]o)/iu', $msg);

        if ($isAskingHowPageWorks) {
            $asksLoginPage = preg_match('/(login|entrar|acessar)/iu', $msg);
            $asksSignupPage = preg_match('/(cadastro|cadastrar|registrar|criar\s+conta)/iu', $msg);
            $asksForgotPage = preg_match('/(esqueci|recuperar|redefinir|senha)/iu', $msg);

            if ($asksLoginPage || $aiOfferedLoginChat) {
                return [
                    'answer' => "Claro! 😊 Funciona assim pela página padrão:

"
                        . "1. Acesse a tela principal da plataforma Nexora BJJ.
"
                        . "2. Clique no botão **Entrar** no topo da página.
"
                        . "3. Você será levado para **/guest/login**.
"
                        . "4. Preencha **e-mail** e **senha** e clique em **Entrar**.

"
                        . "Se quiser, também posso abrir o formulário de login aqui no chat.",
                    'action' => 'page_redirect_guide',
                ];
            }

            if ($asksSignupPage || $aiOfferedSignupChat) {
                return [
                    'answer' => "Claro! 😊 Pelo fluxo da página padrão:

"
                        . "1. Acesse **/guest/login/signup**.
"
                        . "2. Preencha Nome, E-mail, Academia, Senha e Confirmação.
"
                        . "3. Clique em **Cadastrar** para criar sua conta.",
                    'action' => 'page_redirect_guide',
                ];
            }

            if ($asksForgotPage || $aiOfferedForgotChat) {
                return [
                    'answer' => "Claro! 😊 Para recuperar senha pela página:

"
                        . "1. Acesse **/guest/forgot-password**.
"
                        . "2. Informe seu e-mail.
"
                        . "3. Envie o link e finalize a redefinição pelo e-mail.",
                    'action' => 'page_redirect_guide',
                ];
            }
        }

        // Detect explicit user intent switches regardless of previous offers
        $userWantsSignup = preg_match('/\b(cadastro|registrar|cadastrar|criar conta|registro)\b.*\b(aqui|chat|por aqui|agora|online)\b/iu', $msg);
        $userWantsLogin = preg_match('/\b(login|entrar|acessar conta)\b/iu', $msg);

        // If user explicitly requests signup, respond with signup form
        if ($userWantsSignup) {
            return [
                'answer' => "📝 Aqui está o formulário de cadastro para você criar sua conta de forma rápida:",
                'action' => 'show_signup_form',
            ];
        }
        // If user explicitly requests login, respond with login form
        if ($userWantsLogin) {
            return [
                'answer' => "🔐 Aqui está o formulário de login para você acessar sua conta de forma rápida:",
                'action' => 'show_login_form',
            ];
        }
        $userWantsForgot = preg_match('/\b(esqueci|recuperar|redefinir|resetar|reset)\b.*\b(senha)\b/iu', $msg);
        if ($userWantsForgot) {
            return [
                'answer' => "📩 Aqui está o formulário para envio do link de recuperação de senha:",
                'action' => 'show_forgot_password_form',
            ];
        }

        // ---------- Confirmation detection ----------
        // ---------- Confirmation detection ----------
        $isConfirm = preg_match('/^(sim|s|quero|prosseguir|pode ser|abrir|mostrar|confirmar|claro|bora|ok|yes|aceito|desejo|perfeito|pode mandar|manda|gostaria|vamos|vai|pode|ta|tá|vá|va)/iu', $msg)
            || preg_match('/fazer\s+pel[oa]\s+(chat|caht)/iu', $msg)
            || preg_match('/pel[oa]\s+(chat|caht)/iu', $msg)
            || preg_match('/por\s+aqui/iu', $msg);

        if ($isConfirm) {
            if ($aiOfferedLoginChat) {
                return [
                    'answer' => "🔐 Aqui está o formulário de login para você acessar sua conta de forma rápida:",
                    'action' => 'show_login_form',
                ];
            }
            if ($aiOfferedSignupChat) {
                return [
                    'answer' => "📝 Aqui está o formulário de cadastro para você criar sua conta de forma rápida:",
                    'action' => 'show_signup_form',
                ];
            }
            if ($aiOfferedForgotChat) {
                return [
                    'answer' => "📩 Aqui está o formulário para envio do link de recuperação de senha:",
                    'action' => 'show_forgot_password_form',
                ];
            }
        }

            // Não reabra formulário automaticamente sem confirmação explícita do usuário.
            // Isso evita trocar indevidamente login/cadastro em mensagens fora de contexto.


        $pageKeywords = [
            'pagina',
            'página',
            'site',
            'tela',
            'prefiro pelo site',
            'prefiro pela página',
            'pela pagina',
            'pela página',
            'quero pela pagina',
            'quero pela página',
            'na pagina',
            'na página',
            'não, prefiro pelo site',
            'nao prefiro chat',
            'não prefiro chat',
            'fora do chat',
        ];

        $isPageChoice = false;
        foreach ($pageKeywords as $kw) {
            if ($msg === $kw || str_starts_with($msg, $kw . ' ') || str_ends_with($msg, ' ' . $kw) || str_contains($msg, $kw)) {
                $isPageChoice = true;
                break;
            }
        }

        if ($isPageChoice) {
            if ($aiOfferedLoginChat) {
                $appUrl = rtrim($_ENV['APP_FRONTEND_URL'] ?? '', '/');
                $loginPath = '/guest/login';

                return [
                    'answer' => "Claro, sem problema! 😊 Veja o caminho pela página padrão:\n\n"
                        . "1. **Acesse a tela principal** da plataforma Nexora BJJ.\n"
                        . "2. Você verá um botão de **\"Entrar\"** (ou Login) no topo da página — clique nele.\n"
                        . "3. Você será redirecionado para a **[tela de login]({$loginPath})**.\n"
                        . "4. Informe seu **e-mail** e **senha** e clique em **Entrar**.\n\n"
                        . "Pronto! Você estará dentro da plataforma. 🥋\n\n"
                        . "_Se precisar de ajuda em qualquer etapa, é só chamar aqui no chat!_",
                    'action' => 'page_redirect_guide',
                ];
            }
            if ($aiOfferedSignupChat) {
                $signupPath = '/guest/login/signup';

                return [
                    'answer' => "Claro, sem problema! 😊 Veja o caminho pela página padrão:\n\n"
                        . "1. Acesse o link **[Criar Conta]({$signupPath})**.\n"
                        . "2. Preencha seus dados: **Nome**, **E-mail**, **Nome da Academia**, **Senha** e confirme a senha.\n"
                        . "3. Clique em **Cadastrar**.\n\n"
                        . "Pronto! Você poderá acessar a plataforma após o cadastro. 🥋\n\n"
                        . "_Se precisar de ajuda em qualquer etapa, é só chamar aqui no chat!_",
                    'action' => 'page_redirect_guide',
                ];
            }
            if ($aiOfferedForgotChat) {
                $forgotPath = '/guest/forgot-password';
                return [
                    'answer' => "Claro, sem problema! 😊 Veja o caminho pela página padrão:\n\n"
                        . "1. Acesse a **[página de recuperação de senha]({$forgotPath})**.\n"
                        . "2. Informe o **e-mail** da sua conta.\n"
                        . "3. Clique em **Enviar link de recuperação**.\n"
                        . "4. Abra seu e-mail e siga o link para redefinir sua senha.\n\n"
                        . "Pronto! Depois disso, você já pode voltar e fazer login normalmente. 🥋",
                    'action' => 'page_redirect_guide',
                ];
            }
        }

        return null;
    }
}
