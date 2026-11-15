# Grafi — Protocolo de auditoria de interface e código

## Escopo e evidência

Registrar antes de revisar: sistema/tela, fluxo do usuário, stack/versão, viewport(s), tema(s), artefatos inspecionados, estado da informação e tipo de tarefa. A revisão exige evidência compatível com a alegação:
- imagem: evidencia aparência de UM estado numa viewport;
- vídeo/sessão: evidencia interações observadas, não todos os casos;
- código: evidencia decisões estruturais, não a aparência final;
- teste no browser: evidencia o cenário efetivamente executado;
- user research/analytics: evidenciam comportamento se houver método e amostra adequados.

Sem renderização, usar "a verificar visualmente". Sem reprodução de bug, usar "causa provável" em vez de "causa confirmada".

## Ordem de inspeção

### Gate A — Tarefa, conteúdo e hierarquia
- Identificar ação primária da página e primeiras perguntas que ela deve responder.
- O título, contexto e filtros oferecem unidade, período e escopo?
- Elementos do mesmo assunto estão agrupados por proximidade, container, cabeçalho e semântica?
- Diferenciar valores primários, secundários, subtotais e status. Não colocar vários rótulos e números em linhas arbitrárias.
- Conteúdo escaneável em ordem lógica; nomes, quantias e datas são explícitos.

### Gate B — Componente e fluxo
- Página de listagem: busca, ordenação, filtros, paginação/virtualização, estado vazio, seleção e ações são adequados ao volume e tarefa?
- Página de detalhe: descrição, seções, histórico, vínculos, valores, status e ações possuem grupos claros?
- Modal: necessário ou seria melhor página, painel, menu ou toast? Tem título, nome acessível, foco inicial, contenção de foco, Escape quando adequado, retorno ao gatilho e comportamento de scroll?
- Destrutiva: informa consequências e irreversibilidade? Confirmação proporcional antes; mensagem discreta após. Evitar confirm/alert nativo como solução preguiçosa quando há controle de produto próprio.
- Formulário: rótulos, formatos, ajuda, máscaras, validação inline, loading, mensagens e recuperação após erro.
- Estados: vazio, sem correspondência, loading, erro, sucesso, sem permissão, ausência de conexão, restrições e salvamento parcial.

### Gate C — Consistência visual e CSS
- Identificar bibliotecas reais, versões, tokens e componentes reutilizáveis.
- Sem conflitos de especificidade, !important desnecessário, estilos inline arbitrários ou duplicação que diverge entre páginas.
- Espaçamento consistente, alinhamentos, largura de texto, densidade adequada, tamanhos e famílias tipográficas controladas.
- Contraste, cores com significado, hover/focus/disabled, bordas e raios coerentes.
- Transições apoiam contexto sem criar demora; respeitar prefers-reduced-motion.
- Claro/escuro: mesma semântica visual, não simplesmente inverter branco e preto.

### Gate D — Mobile e zoom
- Validar viewport estreita, média e ampla adequadas à aplicação; mínimo recomendado para auditoria inicial: 320/375/768/1280px (ajustar ao projeto).
- Aumentar texto/zoom e verificar reflow; controlar scroll local para tabelas quando intrinsecamente bidimensionais.
- Botões acionáveis, toques com espaço suficiente, alvo e foco claros, inputs sem corte, ordem de leitura e sem headers fixos cobrindo conteúdo.
- Para dashboard, reconsiderar densidade e gráfico no telefone, não apenas reduzir card.
- Modais com conteúdo alto: viewport, teclado virtual, scroll, botão de concluir visível.

### Gate E — Acessibilidade
Alvo recomendado WCAG 2.2 AA; conformidade exige testes de critérios aplicáveis.
- HTML semântico, títulos em ordem, tabelas com cabeçalhos associados e textos alternativos apropriados.
- Navegável por teclado sem armadilha; foco visível; elementos têm nome, função e estado comunicáveis.
- Contraste de texto/controles/estados, informação não dependente só de cor.
- Zoom/reflow, idioma, instruções e prevenção de erro.
- Redução de movimento e alertas dinâmicos acessíveis; aria-live usado com parcimônia.

### Gate F — Dados e gráficos
Usar ../references/data-visualization.md.
- Origem, período, unidade, atualização, recorte, denominadores e sinais validados?
- Tipo de visual corresponde a comparação/evolução/distribuição, sem distorção?
- Eixos, legenda, rótulos, linha de base, comparáveis e escala?
- Ações com drill-down e alternativas textuais/tabela para leitura precisa?

### Gate G — Performance e manutenibilidade
- Loading progressivo/skeleton quando apropriado, evitar saltos de layout.
- Paginação/virtualização onde dados volumosos justificam e sem quebrar semântica/teclado.
- Animar transform/opacity com parcimônia; preferir CSS simples a dependência pesada.
- Consolidar padrões visuais repetidos em componentes, sem abstrair cedo demais.
- Verificar regressões após alteração, inclusive temas e estados críticos.

## Prioridade

- P0 — bloqueio de tarefa, vazamento visual de dados, ação perigosa/enganosa, inacessibilidade grave.
- P1 — falha relevante de leitura, layout quebrado, estado de erro sem saída, dados sem contexto.
- P2 — inconsistência, densidade e fricção evitável.
- P3 — acabamento opcional.

Não compensar um P0 com design bonito. Observações sem evidência devem ser identificadas como hipótese.

## Saída padronizada por achado

**[P1] Falta de agrupamento no resumo do pedido**
Local: modal Detalhes > bloco Informações.
Evidência: rótulo Valor, Quantidade e Total distribuídos em linhas sem hierarquia (observado na tela fornecida).
Impacto: leitura lenta e confusão entre preço unitário e valor total.
Proposta: seção "Valores" com definição semântica (Preço unitário, Quantidade, Total), total em destaque, unidade/moeda claras e composição responsiva.
Validação: inspecionar em 320/375/1280px e com zoom, conferir valores, DOM e ordem de leitura.

## Critério de aprovação

Só declarar "aprovado" depois que bloqueios estiverem resolvidos e a evidência cobrir a tarefa principal, estados relevantes e riscos da mudança. Se só houve revisão de código, emitir "revisão estática concluída; validação visual pendente".
