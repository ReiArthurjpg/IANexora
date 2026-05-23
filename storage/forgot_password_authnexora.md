# Recuperação de Senha na Nexora BJJ

A plataforma Nexora BJJ suporta recuperação de senha de duas formas principais:

## 1. Caminho Padrão do Sistema
- **Recuperar senha**: O usuário pode acessar a tela padrão em `/guest/forgot-password` para solicitar o link de recuperação por e-mail.

## 2. Caminho Interativo pelo Chat (Nativo)
- O usuário pode solicitar a recuperação de senha diretamente na janela do chat, preenchendo um formulário interativo nativo para envio do link.

---

### INSTRUÇÕES DE COMPORTAMENTO PARA A IA:
Quando o usuário perguntar sobre esquecer senha, recuperar senha, redefinir senha ou reset de senha:
1. **Apresente sempre os dois caminhos**: Explique primeiro que ele pode acessar a página padrão de recuperação (use o link [Página de Recuperação de Senha](/guest/forgot-password)) **OU** fazer de forma interativa por aqui pelo chat.
2. **Ofereça e peça confirmação para o chat**: Pergunte explicitamente se ele deseja abrir o formulário interativo diretamente aqui no chat para envio do link de recuperação.
3. **NÃO exiba o formulário de imediato**: Apenas ofereça a opção do chat e aguarde a resposta/confirmação dele.
4. **NUNCA solicite senha por mensagem de texto**: Sob nenhuma circunstância a IA deve pedir senha nova/antiga na conversa por texto. A única forma de recuperação no chat é exibindo o componente visual do formulário.
5. **Se o usuário escolher acessar pela página/tela (ou recusar o chat)**: Mostre o caminho com passos claros para acessar a tela, informar e-mail e enviar o link.

### Exemplo de resposta recomendado para a oferta inicial:
- "Você pode acessar a nossa **[Página de Recuperação de Senha](/guest/forgot-password)** padrão ou, se preferir, posso abrir um formulário interativo diretamente aqui no chat para enviar o link de recuperação. Deseja recuperar a senha por aqui pelo chat?"

### Exemplo de resposta se escolher pela página padrão (ou recusar o chat):
- "Sem problemas! Para acessar pela página padrão, entre em **[Recuperar Senha](/guest/forgot-password)**, informe seu e-mail e clique em enviar. Depois, é só seguir o link recebido no e-mail para redefinir sua senha."
