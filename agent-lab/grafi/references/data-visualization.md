# Grafi — Decisão de visualização de dados e dashboards

Objetivo: apresentar números corretamente para que uma pessoa consiga **entender, comparar e agir**. Referências: NN/g 2017/2022, Datawrapper e guidelines SAP/Odoo (ver modern-enterprise-systems.md).

## Antes do gráfico: 7 perguntas

1. Qual pergunta de negócio deve ser respondida?
2. É um **número pontual**, comparação, evolução, distribuição, composição, relação ou localização?
3. Qual é a unidade, período, denominador, moeda, fuso horário, origem e granularidade?
4. Existe uma linha de base, meta, orçamento, mês anterior ou período comparável?
5. Os dados são completos, confiáveis e recentes? Há "sem dado" versus zero?
6. Quem usa: operação (ação imediata) ou análise (exploração)?
7. Precisa de número exato, tendência visual, detalhes sob demanda ou exportação?

Se faltar contexto essencial, não inventar gráfico nem conclusão.

## Mapa de escolhas

| Pergunta | Padrão inicial | Cuidado |
| --- | --- | --- |
| Quanto tenho agora? | KPI com rótulo, valor, unidade e período | Não esconder significado do saldo nem sinal |
| Estamos melhorando no tempo? | Linha com eixo temporal e referência, se relevante | Não comparar períodos com durações diferentes |
| Quem vendeu mais? | Barras horizontais ordenadas por métrica | Categóricos devem iniciar em zero se comprimento codifica quantidade |
| Orçado versus realizado | Barras agrupadas, tabela com diferença ou bullet chart quando apropriado | Mostrar diferença em moeda e porcentagem, quando faz sentido |
| Como o total se distribui? | Barras empilhadas ou lista de partes, quando comparáveis | Donut só com poucas partes e baixo risco de comparação fina |
| Quais categorias mudaram sua posição? | Small multiples ou múltiplas linhas com rótulos claros | Evitar espaguete de cores |
| Relação entre duas variáveis? | Dispersão | Não sugerir causalidade automaticamente |
| Qual registro exige ação? | Tabela filtrável com status e prioridade | Gráfico não substitui fila operacional |
| Qual o detalhe de um pedido? | Seções + lista de definição + timeline | Não criar uma tabela de uma linha para fatos simples |
| Qual a distribuição? | Histograma com intervalos comparáveis | Não confundir histograma com barras por categoria |
| Temos geografia relevante? | Mapa, só quando localização explica a tarefa | Mapas não são resposta universal para ranking numérico |

## Dashboard operacional vs analítico

Operacional: poucos indicadores críticos, alertas acionáveis, situação atual, prioridade, tempo de atualização e caminho claro para resolver. Evitar excesso de detalhes.

Analítico: filtros de período/coorte, comparação, drill-down, explicações de metodologia, visualizações mais ricas e tabelas de dados subjacentes.

Estrutura recomendada (ajustável): título + definição do escopo; filtro de período compartilhado; KPIs prioritários; visualizações que explicam variação; registros/anomalias que exigem ação. Não inventar densidade para preencher espaço.

## Integridade estatística

- Proibir eixos truncados enganosos para barras; em linhas, escala deve ser informativa e não induzir variações inexistentes.
- Não somar percentuais de denominadores diferentes sem explicar ponderação.
- Comparar intervalos de mesma duração; informar parcialidade do período corrente.
- Datas e moedas devem respeitar localidade e fuso.
- Preservar zero, nulo, pendente e indisponível como estados diferentes.
- Não misturar saldo, receita, despesa, patrimônio e faturamento como se fossem a mesma medida.
- Propor percentuais só com denominador explícito; métricas de taxa exigem base amostral.
- Gráfico deve trazer unidade, legenda/rotulagem, contraste, foco e alternativa acessível (texto/tabela) quando necessário.

## Responsive data visualization

- Compactar legenda/rótulo ou oferecer detalhamento mobile; nunca reduzir fonte até ficar ilegível.
- Redesenhar layout quando o formato deixar de ser interpretável; não apenas comprimir o desktop.
- Verificar interações por toque e teclado, tooltips e foco, zoom e alto contraste.
- Tabelas amplas podem justificar scroll horizontal localizado (WCAG 1.4.10); preservar contexto das colunas.
- Manter gráfico e tabela consistentes em filtro, escopo e resultado.

## Fontes

- https://www.nngroup.com/articles/dashboards-preattentive/ — dashboards operacionais/analíticos (2017)
- https://www.nngroup.com/articles/choosing-chart-types/ — escolha orientada pelo objetivo (2022)
- https://www.datawrapper.de/charts — repertório técnico de gráficos
- https://www.datawrapper.de/docs/how-to-create-a-multiple-columns-chart — múltiplos pequenos (publicado 2025)
- https://www.odoo.com/documentation/19.0/applications/productivity/dashboards/build_and_customize_dashboards.html — fontes, filtros e elementos de dashboards ERP
- https://www.sap.com/design-system/fiori-design-web/v1-84/page-types/floorplans/overview-page — overview por domínio e papel

Os exemplos nesta tabela são **heurísticas de projeto**, não ordens universais nem garantias de eficácia.
