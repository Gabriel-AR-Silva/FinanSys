---
paths:
  - 'resources/js/**'
---

# Js

## Atalhos financeiros abrem a ação correta
O header oferece atalhos de um clique para receita, despesa e transferência. Eles navegam à página de lançamentos já abrindo o modal correspondente; não adicione uma etapa intermediária para escolher novamente a mesma ação.

## Tema usa tokens semânticos
Componentes de interface devem usar os tokens semânticos globais do FinanSys (`--fs-surface`, `--fs-surface-subtle`, `--fs-surface-elevated`, `--fs-border-soft`, `--fs-border`, `--fs-text`, `--fs-text-secondary`, `--fs-text-muted`, `--fs-text-subtle`, `--fs-success`, `--fs-danger`, `--fs-warning`, `--fs-info` e variantes soft) sempre que houver equivalente.

Evite hardcodar branco, slate, gray ou valores hex para fundo, texto, borda, grade, eixos e marcadores de gráficos. Isso vale também para SVG, gradientes, tooltips e estados hover. O componente escolhe a função visual; o tema claro/escuro define a cor final.

Antes de considerar uma tela pronta, valide contraste e legibilidade nos dois temas. Exceções de cor fixa devem ser intencionais e justificadas pela semântica da informação, não pelo tema visual.
