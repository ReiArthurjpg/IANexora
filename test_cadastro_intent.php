<?php
require_once __DIR__ . '/vendor/autoload.php';

// Safe load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

class Tester extends \App\Controllers\ChatController {
    public function testGetAuthIntentFallback(string $message, array $history = []): ?array {
        $reflection = new \ReflectionClass(parent::class);
        $method = $reflection->getMethod('getAuthIntentFallback');
        $method->setAccessible(true);
        return $method->invoke($this, $message, $history);
    }
}

$tester = new Tester();

$testCases = [
    // 0. Explicit request to open forgot-password form
    [
        'message' => 'quero recuperar senha pelo chat',
        'history' => [],
        'expected_action' => 'show_forgot_password_form',
    ],
    [
        'message' => 'abrir formulario de recuperação de senha',
        'history' => [],
        'expected_action' => 'show_forgot_password_form',
    ],
    // 1. Explicit request to open signup form
    [
        'message' => 'quero abrir o formulario de cadastro',
        'history' => [],
        'expected_action' => 'show_signup_form',
    ],
    [
        'message' => 'abrir cadastro no chat',
        'history' => [],
        'expected_action' => 'show_signup_form',
    ],
    [
        'message' => 'cadastrar pelo caht',
        'history' => [],
        'expected_action' => 'show_signup_form',
    ],
    [
        'message' => 'formulario de cadastro',
        'history' => [],
        'expected_action' => 'show_signup_form',
    ],
    // 2. General signup questions (should return offer text, action = null)
    [
        'message' => 'Realização de cadastro',
        'history' => [],
        'expected_action' => null,
    ],
    [
        'message' => 'fazer cadastro',
        'history' => [],
        'expected_action' => null,
    ],
    [
        'message' => 'como se cadastrar?',
        'history' => [],
        'expected_action' => null,
    ],
    [
        'message' => 'criar uma conta',
        'history' => [],
        'expected_action' => null,
    ],
    [
        'message' => 'registrar',
        'history' => [],
        'expected_action' => null,
    ],
    // 3. Confirmations (should return show_signup_form when history offered it)
    [
        'message' => 'sim',
        'history' => [
            ['role' => 'user', 'content' => 'como fazer cadastro'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Cadastro padrão ou, se preferir, posso abrir um formulário de cadastro interativo diretamente aqui no chat para você criar sua conta rapidamente. Deseja realizar o cadastro por aqui pelo chat?']
        ],
        'expected_action' => 'show_signup_form',
    ],
    [
        'message' => 'quero',
        'history' => [
            ['role' => 'user', 'content' => 'fazer cadastro'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Cadastro padrão ou, se preferir, posso abrir um formulário de cadastro interativo diretamente aqui no chat para você criar sua conta rapidamente. Deseja realizar o cadastro por aqui pelo chat?']
        ],
        'expected_action' => 'show_signup_form',
    ],
    [
        'message' => 'pode mandar',
        'history' => [
            ['role' => 'user', 'content' => 'fazer cadastro'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Cadastro padrão ou, se preferir, posso abrir um formulário de cadastro interativo diretamente aqui no chat para você criar sua conta rapidamente. Deseja realizar o cadastro por aqui pelo chat?']
        ],
        'expected_action' => 'show_signup_form',
    ],
    [
        'message' => 'perfeito',
        'history' => [
            ['role' => 'user', 'content' => 'como fazer cadastro'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Cadastro padrão ou, se preferir, posso abrir um formulário de cadastro interativo diretamente aqui no chat para você criar sua conta rapidamente. Deseja realizar o cadastro por aqui pelo chat?']
        ],
        'expected_action' => 'show_signup_form',
    ],
    // 4. Choice for standard page (should return page_redirect_guide when history offered it)
    [
        'message' => 'não, prefiro pelo site',
        'history' => [
            ['role' => 'user', 'content' => 'fazer cadastro'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Cadastro padrão ou, se preferir, posso abrir um formulário de cadastro interativo diretamente aqui no chat para você criar sua conta rapidamente. Deseja realizar o cadastro por aqui pelo chat?']
        ],
        'expected_action' => 'page_redirect_guide',
    ],
    // Additional confirmation cases for signup
    [
        'message' => 'desejo fazer aqui',
        'history' => [
            ['role' => 'user', 'content' => 'como fazer cadastro'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Cadastro padrão ou, se preferir, posso abrir um formulário de cadastro interativo diretamente aqui no chat para você criar sua conta rapidamente. Deseja realizar o cadastro por aqui pelo chat?']
        ],
        'expected_action' => 'show_signup_form',
    ],
    [
        'message' => 'pode abri o formulario aqui',
        'history' => [
            ['role' => 'user', 'content' => 'como fazer cadastro'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Cadastro padrão ou, se preferir, posso abrir um formulário de cadastro interativo diretamente aqui no chat para você criar sua conta rapidamente. Deseja realizar o cadastro por aqui pelo chat?']
        ],
        'expected_action' => 'show_signup_form',
    ],
    // 5. General forgot-password question should return offer text first
    [
        'message' => 'esqueci minha senha',
        'history' => [],
        'expected_action' => null,
    ],
    // 6. Confirmation for forgot-password form after offer
    [
        'message' => 'sim',
        'history' => [
            ['role' => 'user', 'content' => 'esqueci minha senha'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Recuperação de Senha padrão ou, se preferir, posso abrir um formulário interativo diretamente aqui no chat para enviar o link de recuperação. Deseja recuperar a senha por aqui pelo chat?']
        ],
        'expected_action' => 'show_forgot_password_form',
    ],
    // 7. Choice for standard page in forgot-password flow
    [
        'message' => 'prefiro pelo site',
        'history' => [
            ['role' => 'user', 'content' => 'esqueci minha senha'],
            ['role' => 'model', 'content' => 'Você pode acessar a nossa Página de Recuperação de Senha padrão ou, se preferir, posso abrir um formulário interativo diretamente aqui no chat para enviar o link de recuperação. Deseja recuperar a senha por aqui pelo chat?']
        ],
        'expected_action' => 'page_redirect_guide',
    ],
];

$allPassed = true;
foreach ($testCases as $idx => $case) {
    $res = $tester->testGetAuthIntentFallback($case['message'], $case['history']);
    $action = $res['action'] ?? null;
    $passed = $action === $case['expected_action'];
    
    echo "Test Case " . ($idx + 1) . ":\n";
    echo "  Message: '{$case['message']}'\n";
    echo "  Expected Action: " . ($case['expected_action'] ?? 'NULL') . "\n";
    echo "  Actual Action:   " . ($action ?? 'NULL') . "\n";
    if ($passed) {
        echo "  Result: PASS\n";
    } else {
        echo "  Result: FAIL\n";
        $allPassed = false;
        echo "  Answer: " . ($res['answer'] ?? 'NONE') . "\n";
    }
    echo "----------------------------------------\n";
}

if ($allPassed) {
    echo "ALL TESTS PASSED!\n";
} else {
    echo "SOME TESTS FAILED!\n";
}
