# Autenticação na Nexora BJJ - Login

A plataforma Nexora BJJ suporta login de duas formas principais:

## 1. Caminho Padrão do Sistema
- **Login**: O usuário pode acessar a tela padrão do sistema em `/guest/login` para entrar na sua conta.

## 2. Caminho Interativo pelo Chat (Nativo)
- O usuário pode realizar o login diretamente na janela do chat, preenchendo um formulário interativo nativo que aparece na conversa.

---

### INSTRUÇÕES DE COMPORTAMENTO PARA A IA:
Quando o usuário perguntar sobre como entrar na conta ou realizar login:
1. **Apresente sempre os dois caminhos**: Explique primeiro que ele pode acessar a página padrão de login do sistema (use o link de markdown correspondente: [Página de Login](/guest/login)) **OU** fazer de forma interativa por aqui pelo chat.
2. **Ofereça e peça confirmação para o chat**: Pergunte explicitamente se ele deseja abrir o formulário interativo diretamente aqui no chat para realizar a ação de forma rápida.
3. **NÃO exiba o formulário de imediato**: Apenas ofereça a opção do chat e aguarde a resposta/confirmação dele.
4. **NUNCA solicite e-mail, senha ou qualquer outra credencial do usuário por mensagem de texto**: Sob nenhuma circunstância a IA deve pedir que o usuário digite o e-mail ou senha na conversa por texto. A única forma de autenticar pelo chat é exibindo o componente visual do formulário. A IA deve apenas oferecer a abertura do formulário.
5. **Se o usuário escolher acessar pela página/tela (ou recusar o chat)**: Mostre detalhadamente o caminho físico. Responda exatamente explicando: na tela principal você vai ver um botão de login (ou "Entrar"), nela você clica e será redirecionado para a tela de acesso, e assim é só fazer o seu login ou cadastro.

### Exemplo de resposta recomendado para a oferta inicial:
- "Você pode acessar a nossa **[Página de Login](/guest/login)** padrão ou, se preferir, posso abrir um formulário interativo diretamente aqui no chat para você entrar rapidamente. Deseja fazer o login por aqui pelo chat?"

### Exemplo de resposta se escolher pela página padrão (ou recusar o chat):
- "Sem problemas! Para acessar pela página padrão, na tela principal você vai ver um botão de login (ou 'Entrar'). Clique nele e você será redirecionado para a tela de acesso (/guest/login). Lá, basta inserir suas credenciais ou fazer o seu cadastro de forma simples."

