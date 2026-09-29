# Operações locais — Sprint 9

Este documento cobre a operação local do backend Laravel. Não configura deploy externo, provedor de monitoramento, PostgreSQL, e-mail ou binário Tauri.

## Readiness

- `GET /up` é o health check básico do Laravel.
- `GET /api/v1/health` informa que a aplicação está respondendo.
- `GET /api/v1/readiness` testa a conexão e uma consulta ao banco configurado. Retorna `200` com `ready: true` quando o banco está acessível e `503` com `ready: false` quando não está.

## Backup e restauração

Os comandos desta entrega operam somente o driver SQLite configurado. Drivers diferentes são recusados explicitamente; isso não representa suporte ou migração para PostgreSQL.

```powershell
php artisan brindes:backup
php artisan brindes:restore storage/app/backups/database-YYYYMMDD-HHMMSS.sqlite
```

Regras de segurança:

- Em `production`, ambos recusam a operação sem `--force`.
- Backups são gravados em `storage/app/backups`.
- A restauração só aceita um arquivo `.sqlite` existente dentro de `storage/app/backups`.
- A restauração copia primeiro para um arquivo temporário e então faz rename atômico.
- Revise e preserve o backup antes de restaurar; a restauração substitui o arquivo SQLite configurado.
- Não há segredos ou credenciais no artefato.

Exemplo explícito, somente após aprovação operacional:

```powershell
php artisan brindes:backup --force
php artisan brindes:restore storage/app/backups/database-YYYYMMDD-HHMMSS.sqlite --force
```

## Filas e agendamento

A configuração está em `config/queue.php`. O ambiente local de testes usa `QUEUE_CONNECTION=sync`; em um ambiente persistente, configure uma conexão suportada e execute um worker supervisionado:

```powershell
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

O agendador do Laravel deve ser acionado a cada minuto pelo scheduler do ambiente:

```powershell
php artisan schedule:run
```

Para desenvolvimento, `php artisan schedule:work` mantém o loop em execução. O código deste repositório não instala serviço Windows, cron, supervisor ou deploy externo; esses são passos do operador da infraestrutura.

## Verificação local

```powershell
php artisan test tests/Feature/SprintNineOperationsTest.php
vendor/bin/pint --test
```

Os testes cobrem readiness, recusa em produção, recusa de driver não suportado e round-trip de backup/restauração SQLite.
