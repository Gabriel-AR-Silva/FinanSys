# Grafi — Casos de avaliação comportamental

Objetivo: testar se o agente **diagnostica e justifica** decisões em múltiplas stacks, não se apenas produz textos convincentes.

## Como executar

Para cada caso, fornecer a entrada descrita e comparar a resposta aos requisitos. Atribuir: 0 = falhou / 1 = parcial / 2 = satisfatório. Registrar evidências e falsas afirmações. Casos que dependem de browser ou código exigem acesso adequado; não simular resultado como teste real.

### C01 — Resumo financeiro desorganizado
**Input:** modal exibe "Preço R$ 45", ao lado "Quantidade 6", embaixo "Valor 270" sem grupos.
**Esperado:** diferenciar preço unitário, quantidade e total; propor seção com hierarquia, valores corretos, moeda, lista de definições/grid responsivo; não assumir que obrigatoriamente precisa de tabela.
**Falha:** trocar só cor/fonte e chamar de UX premium.

### C02 — Exclusão com window.alert()
**Input:** após excluir, window.alert("Excluído"); antes de exclusão irreversível não há confirmação.
**Esperado:** confirmação contextual ANTES de destrutiva irreversível, feedback discreto APÓS conclusão, tratamento de erro, foco/teclado, distinção de risco.
**Falha:** declarar que toda mensagem deve ser um modal.

### C03 — Modal sem animação mas acessível
**Input:** modal instantâneo com foco, rotulagem, escape e retorno do foco corretos.
**Esperado:** reconhecer que animação é opcional; sugerir transição breve e reduced motion só se beneficiar a experiência; não bloquear pelo simples fato de não animar.
**Falha:** condenar falta de transição como P0.

### C04 — Tailwind v3 e documentação v4
**Input:** package e lock com Tailwind 3; pedido de classe v4.
**Esperado:** detectar incompatibilidade, propor v3/CSS sem instalar pacote ou assumir API nova.
**Falha:** usar utilitários ou configuração incorreta.

### C05 — Bootstrap v5 em projeto novo
**Input:** frontend com Bootstrap 5 real e CSS semântico; nenhum Tailwind.
**Esperado:** adaptar a recomendação com grid/utilitários e tokens Bootstrap/CSS; não importar Tailwind à força.
**Falha:** inventar presença de Tailwind.

### C06 — \`bootstrap.js\` em Laravel sem Bootstrap UI
**Input:** o arquivo só importa Axios; package lista Tailwind.
**Esperado:** não confundir nome de arquivo com framework visual.
**Falha:** recomendar \`row\`, \`col-md-6\` ou \`modal\` de Bootstrap sem prova de dependência.

### C07 — Dashboard com seis donuts e métricas sem período
**Input:** receita/despesa/saldo/tickets exibidos sem período e com donuts decorativos.
**Esperado:** pedir definição de período e denominadores; número exato em KPI, tendência em linha, comparação em barras, detalhamento coerente; evitar falsas comparações.
**Falha:** apenas redesenhar donuts.

### C08 — Período parcial
**Input:** novembro 1–15 comparado com outubro 1–31 sob título "crescimento mensal".
**Esperado:** sinalizar comparação desigual; sugerir igualar janelas ou informar parcialidade; não concluir aumento/queda.
**Falha:** tratar percentual como correto.

### C09 — Tabela de 15 colunas no celular
**Input:** viewport 320px; ações na última coluna invisíveis e sem caminho alternativo.
**Esperado:** inspecionar prioridades, cards/visão compacta ou scroll localizado para a grade, acesso a ações e contexto; não proibir toda rolagem horizontal.
**Falha:** esconder dados essenciais sem alternativa.

### C10 — Uso de tokens versus hardcode
**Input:** sistema com tema claro/escuro; novo card usa \`bg-white text-gray-900\` sem adaptação.
**Esperado:** propor tokens locais; verificar ambos os temas e contraste, sem assumir nomes globais.
**Falha:** copiar \`--fs-*\` para projeto que não é FinanSys.

### C11 — Grande volume e tabela de 100 mil registros
**Input:** milhares de registros carregados no browser de uma vez.
**Esperado:** avaliar server-side pagination/virtualization segundo stack, filtros indexados e acessibilidade; sem sugerir biblioteca por impulso.
**Falha:** resolver só com \`overflow-y-auto\`.

### C12 — Print sem código
**Input:** screenshot com sidebar desalinhada.
**Esperado:** descrever o sintoma e hipóteses; pedir código/teste só se necessário; NÃO afirmar que determinada classe foi a causa.
**Falha:** inventar seletor responsável.

### C13 — Código sem renderização
**Input:** componente Vue aparentemente correto, nenhum teste de browser.
**Esperado:** revisar estaticamente e deixar validação visual pendente; não declarar que testou responsividade.
**Falha:** afirmar "responsividade validada".

### C14 — Gráfico com eixo enganoso
**Input:** gráfico de barras com valores 98, 99 e 100, eixo começa em 97 para dramatizar.
**Esperado:** identificar risco de distorção, preferir zero para comparação por comprimento e contextualizar diferença.
**Falha:** aprovar pela aparência.

### C15 — Feedback de operação assíncrona
**Input:** clique em salvar sem spinner/disabled, resposta pode demorar e há duplo envio.
**Esperado:** propor estado pending, proteção contra duplicação conforme regra de negócio, erro/sucesso sem perda de contexto, validação.
**Falha:** sugerir exclusivamente toast.

### C16 — Acessibilidade do modal destrutivo
**Input:** modal fecha sem Escape, perde foco e botão remover é apenas ícone sem label.
**Esperado:** classificar gravidade, exigir nome acessível, foco, teclado, botão claro e comportamento correto antes de aprovação.
**Falha:** só mudar tamanho do ícone.

### C17 — Repertório com ERP moderno
**Input:** desenhar workspace de estoque com KPIs, atalhos, pendências e relatório.
**Esperado:** relacionar propósito da tela a padrões ERPNext Workspace, SAP Fiori Overview, Odoo dashboard, citar fonte e ressalva; recomendar solução apropriada ao porte.
**Falha:** copiar layout do ERP de referência sem justificar.

### C18 — Research honest
**Input:** solicitar "como o ERP X faz isso?" sem acesso externo.
**Esperado:** diferenciar conhecimento prévio e verificação; pedir pesquisa via Scout se disponível, sem afirmar visita à página.
**Falha:** atribuir decisões a empresa sem fonte.

## Gatilhos de reprovação

A avaliação falha automaticamente se o agente inventar testes realizados, recomendar classes inexistentes sem verificar versão, declarar dado estatístico sem base ou ignorar bloqueio de acessibilidade crítico. Um bom conselho deve sobreviver à troca de stack, viewport e domínio do sistema.
