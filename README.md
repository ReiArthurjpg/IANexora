# AI Knowledge Base API (PHP puro)

API REST profissional em **PHP 8+ sem framework** para armazenar documentos Markdown no MySQL, buscar contexto com SQL LIKE e preparar respostas via Gemini.

## Stack
- PHP 8+
- MySQL (XAMPP)
- Apache
- phpMyAdmin
- Composer
- GuzzleHTTP
- vlucas/phpdotenv
- Swagger/OpenAPI

## Estrutura
Veja as pastas principais em `app/`, `public/`, `storage/` e `docs/`.

## Instalação
1. Clone/copiar projeto para `htdocs` do XAMPP.
2. No terminal:
   ```bash
   cd /caminho/para/htdocs/IANexora
   composer install
   cp .env.example .env
   ```
3. Ajuste `.env` com credenciais locais.

## Configuração do XAMPP
- Inicie **Apache** e **MySQL** no painel do XAMPP.
- Garanta que `mod_rewrite` esteja habilitado (opcional para evolução de rotas).

## Banco de dados
1. Abra `phpMyAdmin`.
2. Importe `app/Database/migrations/001_create_documents.sql`.
3. Isso cria o banco `ai_knowledge_base` e a tabela `documents`.

## Como rodar
Acesse:
- API: `http://localhost/IANexora/public`
- Health: `http://localhost/IANexora/public/api/health`

## Swagger
Acesse a documentação:
- `http://localhost/IANexora/public/swagger`

## Endpoints v1
- `GET /api/health`
- `POST /api/documents/upload`
- `GET /api/documents`
- `GET /api/documents/{id}`
- `DELETE /api/documents/{id}`
- `POST /api/chat`

## Exemplo de chat
Request:
```json
{
  "message": "Onde fica o botão de login?"
}
```

## Segurança aplicada
- Prepared statements via PDO.
- Upload validando extensão `.md`.
- Sanitização do nome de arquivo.
- Variáveis sensíveis via `.env`.

## Evoluções futuras
- Embeddings e busca vetorial.
- Múltiplos providers de IA.
- Autenticação/JWT.
- Workspaces e multiusuário.
