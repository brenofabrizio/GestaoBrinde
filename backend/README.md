# Backend Laravel — Gestão de Brindes

Esta pasta é a nova implementação do backend. O backend PHP antigo continua no diretório raiz durante a migração e não deve ser apagado até que os fluxos sejam validados.

## Objetivo desta primeira sprint

- retirar o estado operacional do filesystem temporário da Vercel;
- usar PostgreSQL como fonte única e persistente;
- registrar entradas e saídas dentro de transações com `lockForUpdate()`;
- manter cada movimentação como lançamento imutável do livro;
- impedir lançamentos duplicados com `idempotency_key`;
- expor uma API versionada em `/api/v1`.

## Execução local

Requisitos: PHP 8.3+, Composer e PostgreSQL. Laravel 13 requer PHP 8.3 ou superior.

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Os endpoints públicos de verificação são `GET /up`, `GET /api/v1/health` e `GET /api/v1/readiness`. O runbook de backup, restauração, filas e scheduler está em `docs/operations-sprint-9.md`.

Para testar na Vercel, configure o Root Directory do projeto Vercel como `backend` e forneça as variáveis PostgreSQL no ambiente da Vercel. Não use SQLite, JSON gravável ou o filesystem da função como banco.

## Persistência

JSON do projeto antigo não é usado como banco operacional. Ele pode ser importado pelo comando controlado abaixo. Todas as alterações de estoque deste backend passam pelo PostgreSQL e por `InventoryService`.

Para simular a importação sem gravar:

```powershell
php artisan brindes:import-json --dry-run
```

O comando aceita `--path=<diretório>` para fixtures ou uma origem exportada. Ele importa `solicitacoes.json`, `solicitacao_itens.json`, `movimentacoes.json` e `auditoria.json` além de cadastros e brindes; `usuarios.json` é sempre ignorado. Os aliases legados aceitos por fixture são `id`/`solicitacao_id`, `codigo`, `usuario_id`, `finalidade`, `brinde_id`, `quantidade`, `tipo`, `saldo_apos` e `acao`. Registros sem referências ou dados obrigatórios são ignorados e listados, em ordem determinística, em `import_invalid.json` no diretório de entrada. O resumo informa totais importados e saldos por código. Senhas, hashes, tokens e conteúdo arbitrário de auditoria não são copiados.

Os JSON versionados no repositório estão vazios; os aliases acima descrevem os campos cobertos pelos testes de fixture, não garantem a estrutura de arquivos legados ainda não fornecidos.

Para importar depois de revisar o relatório:

```powershell
php artisan brindes:import-json
```

## Próximas sprints

1. concluir importação de solicitações, movimentações e auditoria;
2. migrar escopo por indústria e troca obrigatória de senha;
3. conectar PDF, assinatura e armazenamento de arquivos;
4. conectar o frontend atual à API Laravel;
5. configurar deploy do backend em serviço PHP persistente e deixar a Vercel somente para o frontend;
6. empacotar o cliente desktop com Tauri.
