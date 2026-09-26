# PRD — Controle de Brindes

## 1. Visão do produto

O Controle de Brindes organiza o ciclo completo de brindes da empresa: cadastro, estoque, solicitação TRADE, aprovação, compra, recebimento no CD, retirada, entrega, comprovante e auditoria.

O produto atende computadores e tablets pelo navegador. A implantação oficial deve usar PHP + MySQL/MariaDB. A Vercel permanece como ambiente de demonstração/homologação com SQLite.

## 2. Problema

O processo depende de informações espalhadas entre planilhas, mensagens, chamados e controles locais. Isso dificulta saber o saldo real, localizar uma solicitação, acompanhar a aprovação e comprovar a retirada.

## 3. Objetivos

- Centralizar estoque, solicitações e aprovações em um único sistema.
- Reduzir erro de saldo, duplicidade e retirada sem autorização.
- Conectar a abertura de chamados ao Lecom, mantendo a chave somente no servidor.
- Funcionar em computador e tablet, com tolerância a perda temporária de conexão.
- Enviar notificações ao solicitante e aos responsáveis.
- Manter histórico, auditoria e comprovante digital.

## 4. Fora do escopo inicial

- Substituir o Lecom como motor BPM.
- Emitir NF ou substituir o ERP.
- Criar um aplicativo nativo `.exe` nesta fase.
- Treinar ou hospedar uma IA antes de estabilizar dados, permissões e integrações.

## 5. Usuários e permissões

| Perfil | Necessidade principal |
|---|---|
| Administrador | Configurar o sistema, usuários, permissões, backup e integrações |
| TRADE / Gestor | Criar e aprovar solicitações, acompanhar compras, estoque e eventos |
| TRADE / Solicitante | Criar e acompanhar suas solicitações |
| CD / Estoque | Receber, movimentar, separar, confirmar saída e registrar retirada |
| Indústria | Consultar informações permitidas da própria indústria |

## 6. Escopo funcional

### P0 — operação essencial

- Login, sessão, recuperação e troca de senha.
- Cadastros de brindes, categorias, indústrias, departamentos, locais e fornecedores.
- Saldo de estoque, entrada, saída, ajuste, transferência e livro de movimentações.
- Solicitações internas e solicitações TRADE.
- Aprovação com regras configuráveis.
- Recebimento no CD com NF.
- Retirada por QR Code e assinatura.
- Protocolo digital em PDF.
- Auditoria e permissões.
- Abertura de instância Lecom pela tela Nova solicitação TRADE.

### P1 — operação assistida

- Fila offline no navegador com IndexedDB e sincronização posterior.
- Espelho JSON para inspeção, exportação e backup local.
- Notificações internas e fila de e-mail.
- Dashboard, relatórios e alertas de estoque.
- Backup e restauração SQL.

### P2 — evolução

- Integração OAuth/Graph com Microsoft 365.
- Worker de sincronização mais robusto para conflito offline.
- Serviço de IA isolado para busca, resumo, previsão e apoio operacional.
- Empacotamento como aplicativo desktop depois que a versão web estiver estável.

## 7. Critérios de sucesso

- Uma solicitação TRADE percorre criação, aprovação, compra, recebimento e retirada sem planilha paralela.
- Cada baixa de estoque possui usuário, motivo, origem e timestamp.
- O duplo clique não gera uma segunda movimentação.
- O usuário entende quando está offline e vê operações pendentes.
- O chamado Lecom abre diretamente a partir de Nova solicitação.
- O solicitante recebe notificações de mudança de estado quando o SMTP estiver habilitado.

## 8. Requisitos não funcionais

- PHP 8.2 ou superior, com compatibilidade validada também em PHP 8.5.
- MySQL 8.0.16+ ou MariaDB 10.4+ em produção.
- HTTPS obrigatório em produção.
- Segredos fora do Git e fora do navegador.
- CSRF, sessões HttpOnly, permissões por ação e auditoria.
- Layout responsivo para tablet e desktop.
- Respostas JSON padronizadas na API.

## 9. Dependências externas

- Lecom: processo configurado, publicado e API key do ambiente correto.
- Microsoft 365: mailbox de envio, SMTP AUTH habilitado ou autenticação OAuth/Graph aprovada.
- Banco relacional de produção e armazenamento persistente para uploads, PDFs e backups.

## 10. Riscos e mitigação

| Risco | Mitigação |
|---|---|
| SQLite efêmero na Vercel | Usar Blob apenas para demo e MySQL persistente na implantação oficial |
| SMTP AUTH bloqueado no Microsoft 365 | Usar mailbox dedicado e habilitar apenas o necessário, ou implementar OAuth/Graph |
| Conflitos offline | Idempotency-Key, fila ordenada, estados pendente/falha e revisão de conflitos |
| Permissões divergentes do Lecom | Validar processo, versão, ambiente e usuário antes do go-live |
| Crescimento de regra no PHP | Manter Services isolados e preparar contratos para serviços externos |

## 11. Aceite do produto

O produto é considerado pronto para a primeira operação quando os cenários P0 passam em `php tests/scenarios.php`, o teste de e-mail é aprovado, o chamado Lecom abre em homologação, o backup é restaurado em ambiente de teste e os perfis não acessam dados fora do seu escopo.
