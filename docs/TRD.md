# TRD — Documento Técnico de Requisitos

## 1. Baseline técnico

- Backend: PHP puro 8.2+, MVC pequeno do projeto, PDO e Composer.
- Frontend: HTML server-rendered, Bootstrap, Alpine.js e JavaScript próprio.
- Banco oficial: MySQL/MariaDB com InnoDB e `utf8mb4`.
- Banco demo: SQLite reconstruído/hidratado na Vercel.
- Assets: `public/assets/` sem build obrigatório de frontend.
- Dependências: PHPMailer, Dompdf, PhpSpreadsheet e mysqldump.

## 2. Camadas

```text
Browser / tablet
  -> public/index.php
  -> App + Router + Auth + CSRF
  -> Controllers
  -> Services / Workflow / Notification
  -> PDO Db
  -> MySQL/MariaDB
```

As páginas são cascos HTML. O JavaScript da página consome `/api/...`. Regras de negócio ficam em `app/Services`, nunca no template.

## 3. Contratos principais

### Solicitação TRADE

- `POST /api/trade/requests`: cria a solicitação.
- `GET /api/requests?flow=trade`: lista solicitações TRADE autorizadas.
- `GET /api/requests/{id}`: retorna detalhe, itens, histórico, ação disponível e QR.
- `POST /api/trade/requests/{id}/approve`: aprova e registra o chamado de compra.
- `POST /api/trade/requests/{id}/receive`: registra recebimento e NF.
- `POST /api/trade/requests/{id}/withdraw`: registra retirada com assinatura.

### Lecom

O sistema usa duas camadas:

1. Workspace no navegador, quando o cookie `LecomSSOTicket` está disponível.
2. Fallback server-to-server com `POST /service/bpm/api/v1/process-instances`.

O fallback usa `LECOM_API_KEY`, `X-Server`, processo e versão configurados por ambiente. Para homologação, `LECOM_TEST_MODE=true` adiciona `test-mode: true`; `LECOM_TEST_USER` é opcional. Nenhuma chave é enviada ao browser.

## 4. Persistência e offline

- O banco relacional é a fonte de verdade.
- O navegador usa IndexedDB (`gestao-brindes-offline`) para uma outbox de operações replayáveis.
- A API usa `Idempotency-Key` nas operações que podem ser repetidas.
- `JSON_DB_MIRROR=true` gera arquivos JSON por tabela no servidor, para inspeção e backup.
- O espelho JSON não substitui o banco relacional e não deve ser tratado como mecanismo de concorrência.

## 5. Segurança

- `.env` e variáveis da plataforma guardam segredos.
- `LECOM_API_KEY`, SMTP password e `APP_KEY` nunca entram em logs, HTML ou Git.
- CSRF obrigatório em escritas.
- Sessão HttpOnly, SameSite=Lax e Secure com HTTPS.
- Permissões verificadas no backend em cada endpoint.
- Uploads ficam fora da pasta pública.
- Auditoria registra alterações sensíveis e movimentações.

## 6. Observabilidade

- Logs em `storage/logs/` localmente e stderr no ambiente serverless.
- Logs devem registrar rota, usuário, status e código técnico, sem payloads sensíveis.
- A mensagem exibida ao usuário deve ser funcional; a causa técnica fica no log.
- Health check: `GET /api/health`.

## 7. E-mail Microsoft 365

O código atual usa PHPMailer com usuário e senha SMTP. Para Microsoft 365, a configuração esperada para SMTP client submission é:

```env
MAIL_DRIVER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=caixa-do-sistema@empresa.com.br
MAIL_PASSWORD=segredo-fora-do-Git
MAIL_FROM_ADDRESS=caixa-do-sistema@empresa.com.br
```

Antes do teste final, a TI precisa confirmar `Authenticated SMTP` para a mailbox e a política do tenant. A Microsoft recomenda Modern Auth/OAuth; se a empresa bloquear autenticação básica, o próximo trabalho técnico será trocar a autenticação SMTP por OAuth2 ou Microsoft Graph. Também é necessário configurar o cron/worker `cron/send_notifications.php` para processar a fila.

Fontes oficiais: [SMTP AUTH no Exchange Online](https://learn.microsoft.com/en-us/exchange/clients-and-mobile-in-exchange-online/authenticated-client-smtp-submission), [configuração de aplicativos Microsoft 365](https://learn.microsoft.com/en-us/exchange/mail-flow-best-practices/how-to-set-up-a-multifunction-device-or-application-to-send-email-using-microsoft-365-or-office-365) e [OAuth para SMTP](https://learn.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth).

## 8. Evolução para IA

O backend PHP continua como dono de autenticação, transações e regras. Uma futura camada Python pode consumir APIs internas por uma conta de serviço, com Celery para tarefas longas e LangChain somente quando houver casos de uso, dados e avaliações definidos. A IA não deve escrever diretamente nas tabelas nem contornar permissões.
