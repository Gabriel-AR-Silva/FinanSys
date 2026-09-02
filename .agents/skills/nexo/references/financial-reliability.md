# Confiabilidade financeira aplicável ao FinanSys

Pesquisa revisada em 2026-09-01. Preserve simplicidade: adote mecanismos apenas
quando houver uma falha concreta que eles resolvam.

## Idempotência

- Uma operação mutável recebe `operation_id` estável, reutilizado em retries.
- Mesma chave e mesmos parâmetros retornam o mesmo efeito; mesma chave com
  parâmetros diferentes é rejeitada.
- Compare usuário, natureza, origem, destino e valor.
- A constraint única é a barreira final. Em corrida simultânea, após violação
  de unicidade, releia e devolva o resultado apenas se os parâmetros coincidirem.
- Teste repetição sequencial, simultânea, chave incompatível e falha parcial.

Evidência: [Stripe Idempotent Requests](https://docs.stripe.com/api/idempotent_requests).

## Transações, filas e efeitos externos

- Mantenha a operação financeira inteira em uma transação curta.
- Não aguarde rede, usuário ou e-mail dentro da transação.
- Despache jobs após commit; jobs mutáveis devem tolerar repetição e reler o
  estado confirmado por identificador.
- Outbox só se justifica quando um efeito externo durável for obrigatório e a
  perda do evento for inaceitável. Saga, event sourcing e broker dedicado são
  prematuros para o monólito local.

Evidências: [Laravel queues e transações](https://laravel.com/framework/docs/12.x/queues#jobs-and-database-transactions)
e [AWS Transactional Outbox](https://docs.aws.amazon.com/en_en/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html).

## Exclusão, restauração e retenção

- Soft delete não fornece cascata automática. Conta, caixinhas e lançamentos
  devem compartilhar `deletion_batch_id` e mudar numa única transação.
- Restaure apenas filhos apagados pelo mesmo lote; nunca reviva exclusões antigas.
- Interface e backend devem recusar restauração após 30 dias.
- Antes da produção, implemente purga explícita, auditável e reiniciável; a
  retenção do log de auditoria é uma decisão separada.
- Teste antes, no limite e depois do prazo, falha intermediária e repetição da purga.

Evidência: [Laravel Soft Deletes](https://laravel.com/docs/12.x/eloquent#soft-deleting).

## Concorrência

Constraints permanecem obrigatórias. Não adicione locks enquanto nenhuma regra
depender de saldo disponível. Quando isso surgir, bloqueie recursos numa ordem
determinística e use retry limitado para deadlock/serialization failure. Valide
no mesmo banco escolhido para produção; SQLite não reproduz essa concorrência.

Evidências: [PostgreSQL Explicit Locking](https://www.postgresql.org/docs/current/explicit-locking.html)
e [Transaction Isolation](https://www.postgresql.org/docs/current/transaction-iso.html).
