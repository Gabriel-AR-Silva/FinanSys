---
paths:
  - 'app/Models/Category.php,app/Http/Requests/StoreLedgerEntryRequest.php'
---

# Requests

## Categorias de lançamentos são reutilizáveis e isoladas
Categorias pertencem ao usuário e têm natureza income ou expense. Novos lançamentos manuais exigem uma categoria do próprio usuário compatível com o tipo; registros históricos podem permanecer sem categoria. A descrição é apenas um detalhe opcional.
