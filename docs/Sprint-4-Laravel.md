# Sprint 4 — Eventos, entregas e protocolos

## Entregue

- eventos planejados, abertos e encerrados;
- cotas de brindes por indústria e evento;
- protocolo persistente de entrega;
- baixa de estoque dentro da mesma transação do protocolo;
- hash da assinatura e hash de verificação;
- idempotência por chave da retirada;
- bloqueio de baixa duplicada após reenvio ou nova leitura do QR;
- URL de QR assinada por sete dias;
- verificação pública limitada a protocolo, tipo e data, sem dados pessoais ou estoque;
- endpoints de entregas e eventos na API Laravel.

## Critério de aceite

Uma retirada aprovada gera exatamente um protocolo, uma movimentação por item e o saldo atualizado. Repetir a mesma chave de idempotência retorna o protocolo existente sem nova baixa.

## Pendente

O QR visual e a geração de PDF ainda serão conectados ao frontend e ao armazenamento de arquivos na próxima etapa.
