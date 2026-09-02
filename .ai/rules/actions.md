---
paths:
  - app/Actions/TransferFunds.php
---

# Actions

## Transferências preservam saldo e impedem descoberto
Transferências imediatas podem ocorrer entre conta e/ou caixinha ativas do mesmo usuário. A origem e o destino devem ser diferentes; o valor, positivo com até 2 casas, pode zerar a origem mas nunca superar seu saldo derivado direto. Cada transferência cria duas pernas iguais e auditadas atomicamente e não altera o saldo geral.
