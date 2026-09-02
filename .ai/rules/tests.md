---
paths:
  - 'tests/**'
---

# Tests

## Cobertura mínima de transferências
Cobrir as quatro combinações conta/caixinha, saldo exato, saldo insuficiente, valor inválido, origem=destino, isolamento A/B, inativos/excluídos, replay idempotente e chave divergente. Falha deve deixar zero escrita parcial; SQLite não é evidência de concorrência do banco de produção.
