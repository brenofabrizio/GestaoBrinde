# Plano de sprints — Reestruturação Laravel

## Sprint 0 — Diagnóstico e arquitetura

**Status:** Concluída

- diagnóstico do backend PHP próprio;
- identificação do problema de SQLite/snapshot na Vercel;
- definição de Laravel, API versionada e banco relacional futuro;
- decisão de manter o legado até a validação.

## Sprint 1 — Núcleo Laravel e estoque

**Status:** Implementada; execução local pendente

- estrutura Laravel em `backend/`;
- migrations de cadastros, brindes e estoque;
- `InventoryService` transacional;
- livro imutável;
- idempotência;
- endpoints de entrada, saída e consulta.

## Sprint 2 — Identidade e permissões

**Status:** Implementada; execução local pendente

- Sanctum;
- login/logout/me;
- cinco perfis;
- permissões granulares;
- middleware de autorização;
- importador inicial de cadastros e brindes;
- `--dry-run` para importação.

## Sprint 3 — Solicitações TRADE

**Status:** Implementada; execução local pendente

- solicitação e itens;
- cálculo de valor e necessidade de compra;
- envio para aprovação;
- aprovação/reprovação;
- histórico de status;
- escopo por solicitante e indústria.

## Sprint 4 — Eventos, entregas e QR

**Status:** Implementada parcialmente

- eventos e cotas;
- protocolo de entrega;
- baixa transacional;
- idempotência da retirada;
- QR assinado e verificação mínima.

Pendente dentro desta sprint: geração visual do QR, PDF, assinatura persistida e armazenamento de arquivos.

## Sprint 5 — Migração completa de dados

**Status:** Pendente

- importar solicitações;
- importar itens de solicitações;
- importar movimentações;
- importar auditoria;
- gerar relatório de registros inválidos;
- não importar usuários sem senha válida;
- validar contagem e saldos antes/depois.

## Sprint 6 — Segurança e experiência de acesso

**Status:** Pendente

- troca obrigatória de senha;
- recuperação de senha;
- convite de usuário;
- escopo completo por indústria;
- auditoria de login e alterações;
- expiração/revogação de tokens;
- rate limit e proteção contra tentativas excessivas.

## Sprint 7 — Lecom, notificações e relatórios

**Status:** Pendente

- integração de abertura de instância Lecom;
- configuração do portal em Configurações;
- notificações internas;
- SMTP/Graph;
- relatórios de estoque, solicitações e entregas;
- dashboard operacional.

## Sprint 8 — Frontend e Vercel

**Status:** Pendente

- trocar chamadas do frontend legado para `/api/v1`;
- corrigir contratos de erro e loading;
- validar perfis no menu e no backend;
- configurar CORS e domínio;
- apontar homologação Vercel para `backend`;
- executar migrations e seed no ambiente;
- teste completo dos cinco perfis.

## Sprint 9 — Operação corporativa e `.exe`

**Status:** Pendente

- PostgreSQL gerenciado;
- backups e restauração;
- observabilidade;
- fila de tarefas;
- build desktop com Tauri;
- modo remoto e eventual modo local/offline;
- manual de implantação e suporte.

## Critérios para trocar o backend legado

1. migrations executadas sem erro;
2. testes automatizados aprovados;
3. entradas e saídas persistem após reinício/requisição;
4. estoque, livro e histórico batem;
5. retirada duplicada bloqueada;
6. perfis validados;
7. importação conferida;
8. frontend conectado;
9. deploy de homologação aprovado;
10. backup e restauração testados.

Até todos os critérios serem atendidos, o backend legado deve permanecer disponível apenas como referência e fallback controlado.
