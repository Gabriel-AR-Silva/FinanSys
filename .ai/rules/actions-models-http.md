---
paths:
  - 'app/{Actions,Models,Http}/**/*ReceiptForecast*.php'
---

# Actions Models Http

## Vínculos de previsões e receitas são integrais e reversíveis
P1 aprovado: um lançamento real de receita pertence integralmente a no máximo uma previsão; uma previsão aceita vários lançamentos parciais. Pendência = max(previsto - soma dos vínculos ativos, 0); excedente é só informativo. Desfazer recalcula a pendência, remarca apenas o residual e restauração de vínculo exige confirmação e versão.
