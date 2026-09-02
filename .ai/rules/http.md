---
paths:
  - 'app/Http/**'
---

# Http

## Contrato HTTP de transferência
O endpoint autenticado recebe source_type/source_id, destination_type/destination_id, amount e operation_id UUID obrigatório. Referências são resolvidas somente dentro do usuário autenticado; excluídas/inativas/alheias são recusadas sem revelar existência. No MVP não há data, descrição livre, agendamento, edição ou estorno.
