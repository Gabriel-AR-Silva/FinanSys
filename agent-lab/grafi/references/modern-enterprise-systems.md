# Grafi — Referências de produtos e sistemas empresariais modernos

Atualizado em: 2026-11-15. Recorte: edições, interfaces, padrões e documentação usados entre 2017 e 2026. Alguns produtos existem há mais de dez anos; **não** inferir que o produto foi criado no período. O recorte corresponde às versões e referências contemporâneas, quando identificáveis.

## Como usar esta biblioteca

Cada entrada descreve um **padrão observável na documentação do fornecedor**, sua aplicação potencial e uma ressalva. Isto NÃO é prova independente de eficácia ou de qualidade visual. Ao projetar uma tela: identificar a tarefa, escolher referências pertinentes, comparar alternativas, propor padrão e validar com os usuários e dados reais. Não copiar cores, identidade, componentes proprietários ou códigos indiscriminadamente.

### 1) Odoo 19 — Dashboards ERP alimentados por planilhas

- Fonte primária: https://www.odoo.com/documentation/19.0/applications/productivity/dashboards/build_and_customize_dashboards.html
- Recorte: documentação da versão 19; aplicação de negócios/ERP.
- Fato documentado: painéis são construídos sobre planilhas, com fontes conectadas a lista, tabela dinâmica e gráfico; suportam filtros globais, links e elementos de dashboard. A documentação distingue a folha de apresentação e as folhas auxiliares de cálculo.
- Padrão para Grafi: separar **modelo/consulta/cálculo** da **superfície que apresenta métricas**. Agrupar KPIs por pergunta de negócio, usar filtros compartilhados coerentes e permitir drill-down para o registro que explica o número.
- Não copiar: uma planilha embutida pode ser complexa demais para um dashboard simples; não pressupor que todo projeto precise de motor de planilhas.
- Pergunta de revisão: de onde veio cada número, qual filtro mudou seus valores e para qual detalhamento o usuário pode navegar?

### 2) ERPNext / Frappe — Workspace e navegação por domínio

- Fontes primárias:
  - https://docs.frappe.io/erpnext/workspace
  - https://docs.frappe.io/framework/user/en/desk/navigation
  - https://frappe.io/framework/desk-ui
- Recorte: documentação atual consultada em 2026; versão de desenvolvimento/implantação concreta deve ser conferida.
- Fato documentado: o Workspace reúne dashboard de um módulo, atalhos e links para cadastros/relatórios; a navegação atual é organizada em app > módulo > itens na barra lateral; o framework oferece vistas lista, formulário, relatório, calendário e Kanban.
- Padrão para Grafi: organizar o ERP pelo **modelo mental de trabalho** (por exemplo, Estoque, Vendas, Financeiro), não pelo número bruto de rotas. Diferenciar portal de navegação, painel de indicadores, listagem e detalhe.
- Não copiar: mais níveis de navegação não são automaticamente melhores; para sistemas pequenos, simplificar.
- Pergunta de revisão: o usuário encontra as três ações frequentes do seu módulo sem percorrer menus irrelevantes?

### 3) SAP Fiori elements — Tipos de página para tarefas distintas

- Fontes primárias:
  - https://www.sap.com/design-system/fiori-design-web/v1-145/discover/frameworks/sap-fiori-elements
  - https://www.sap.com/design-system/fiori-design-web/v1-84/page-types/floorplans/overview-page
- Recorte: design system contemporâneo documentado em versões de guidelines consultadas em 2026; SAP Fiori como família é anterior ao recorte, não classificá-lo como produto criado em 2016–2026.
- Fato documentado: há padrões List Report/Worklist para coleções e ações; Object Page para registro detalhado; Analytical List Page para explorar e filtrar dados e chegar aos detalhes; Overview Page para reunir informações acionáveis de um domínio ou papel.
- Padrão para Grafi: antes de desenhar, selecionar o **tipo de página**: painel operacional, listagem, análise ou detalhe. Não espremer todas as funções em um mesmo card gigante.
- Não copiar: a estrutura SAP é mais pesada que algumas aplicações pequenas; aplicar princípios, não complexidade gratuita.
- Pergunta de revisão: a tela tenta ser ao mesmo tempo dashboard, cadastro, relatório e editor sem hierarquia?

### 4) Shopify Admin — Gestão de coleções com ações em massa

- Fonte primária: https://shopify.dev/docs/api/app-home/latest/patterns/compositions/index-table
- Recorte: documentação atual do ecossistema Shopify Admin consultada em 2026.
- Fato documentado: Index Table reúne listagem escaneável, busca, filtros, ordenação, seleção, ações em massa, paginação, confirmação de ações destrutivas e feedback após a operação.
- Padrão para Grafi: em CRUDs densos, manter filtros e ações relevantes perto dos resultados, exibir o número selecionado e distinguir confirmação de ação do feedback de conclusão.
- Não copiar: a API de componentes do Shopify não está disponível universalmente; replicar o comportamento em componentes existentes da stack.
- Pergunta de revisão: o usuário sabe quantos itens estão selecionados e o alcance de uma exclusão em massa?

### 5) Carbon (IBM) — Data Table e descoberta progressiva

- Fonte primária: https://www.carbondesignsystem.com/building-blocks/core/components/data-table/guidelines
- Recorte: documentação atual com data de atualização visível na página (30/09/2026), consultar versão pertinente.
- Fato documentado: a tabela suporta ordenação, expansão de linhas, ferramentas, seleção, ações individuais e em lote; desaconselha uso como substituto de planilhas.
- Padrão para Grafi: tabela com colunas semanticamente claras, ações e estado vazio; dados secundários podem ficar em expansão/detalhe para reduzir poluição.
- Não copiar: nem toda tabela precisa de filtros, seleção e expansão; implementar apenas se a tarefa os justificar.
- Pergunta de revisão: há informação repetida demais em cada linha ou todas as colunas ajudam uma decisão?

### 6) Microsoft Fluent 2 — Proximidade, hierarquia e grids

- Fonte primária: https://fluent2.microsoft.design/layout
- Recorte: linguagem de design Fluent 2, documentação atual consultada em 2026.
- Fato documentado: espaçamento e proximidade comunicam relacionamento entre elementos; grids e regiões criam hierarquia e se adaptam a telas diferentes.
- Padrão para Grafi: agrupar preço, quantidade, moeda, valor total e status por significado; usar grid fluido e espaçamento consistente, em vez de pares de rótulo/valor distribuídos arbitrariamente.
- Não copiar: nem sempre um grid de 12 colunas é ideal; seguir conteúdo, densidade e dispositivo.
- Pergunta de revisão: ao olhar a página por 3 segundos, fica evidente qual valor é o principal e quais dados pertencem ao mesmo objeto?

### 7) Atlassian — Dynamic table

- Fonte primária: https://atlassian.design/components/dynamic-table/
- Recorte: documentação atual consultada em 2026.
- Fato documentado: o componente contempla ordenação, paginação e reordenação de linhas.
- Padrão para Grafi: não recriar várias tabelas incompatíveis onde um componente reutilizável, testado e compatível com a stack resolveria.
- Não copiar: reordenação não é requisito automático; pode atrapalhar ordenação e acessibilidade se não for pensada.

### 8) Supabase Design System — Tabela vs Data Table vs Data Grid

- Fonte primária: https://supabase.com/design-system/docs/ui-patterns/tables
- Recorte: documentação atual consultada em 2026; data inicial da publicação não confirmada.
- Fato documentado: diferencia tabela estática, tabela de dados com pesquisa/filtros/paginação e grade interativa com recursos avançados.
- Padrão para Grafi: escolher a sofisticação pela tarefa. Não usar um grid altamente interativo quando uma tabela semântica simples resolve.
- Não copiar: não sugerir dependências de tabela sem comprovar manutenção, licença e compatibilidade.

## Pesquisa editorial e fundamentos (pós-2016)

### Nielsen Norman Group: dashboards que podem ser compreendidos rapidamente

- Artigo: https://www.nngroup.com/articles/dashboards-preattentive/
- Autoria: Nielsen Norman Group; data 18/06/2017.
- Distinção relevante: dashboards operacionais e analíticos respondem a ritmos de decisão diferentes. Um dashboard não é um portal com toda a aplicação.
- Uso: selecionar poucas perguntas prioritárias, distinguir monitoramento de exploração detalhada, apoiar leitura pré-atentiva sem efeitos supérfluos.

### Kate Moran (Nielsen Norman Group): gráficos precisam de contexto

- Artigo: https://www.nngroup.com/articles/choosing-chart-types/
- Autora: Kate Moran; 30/01/2022.
- Princípio: antes de decidir o gráfico, determinar o que a audiência precisa compreender e fazer; aplicar contexto, redução de ruído e contraste útil.
- Uso: não apresentar 8 donuts só porque há espaço; escolher visual que responde a pergunta.

### Datawrapper — Catálogo de gráficos claros e responsivos

- Referência: https://www.datawrapper.de/charts
- Padrão: variedade de gráficos, anotações, destaque e contexto. Usar como catálogo de possibilidades e linguagem de componentes, não como endosso a um gráfico específico.
- Exemplo recente (2025): https://www.datawrapper.de/docs/how-to-create-a-multiple-columns-chart
- Uso: avaliar *small multiples* quando séries numerosas se confundem em gráficos agrupados; manter rótulos legíveis em mobile.

## Fontes normativas para decisões verificáveis

- W3C WCAG 2.2 (2023 e atualizações): https://www.w3.org/TR/WCAG22/
- W3C WAI-ARIA Dialog Modal Pattern: https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
- São guias de acessibilidade; diferente de exemplos visuais, sustentam verificações objetivas de semântica, foco, teclado e adaptação.

## Critérios de curadoria futura

1. Priorizar documentação do produto e artigos de autores identificados, quando pertinentes.
2. Registrar data da versão, data de publicação e data de consulta separadamente. Produto antigo pode ter UI de versão recente.
3. Documentar *o que está realmente na fonte* versus *inferência/recomendação do Grafi*.
4. Toda referência deve responder a uma pergunta de projeto (quando usar, quando evitar, como validar).
5. Buscar um contraexemplo de cada solução antes de promovê-la a regra universal.
6. Preferir comparar alternativas em contexto real a replicar screenshots. Não reproduzir propriedade intelectual de interface sem autorização.
7. Se o Scout estiver acessível, pedir pesquisa profunda específica para lacunas (ERP mobile, relatórios complexos, acessibilidade de gráficos, etc.). Sem acesso, sinalizar limite.

**Resultado esperado:** repertório de produtos modernos para decisões melhores, não uma coleção de telas bonitas sem contexto.
