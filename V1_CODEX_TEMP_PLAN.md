# Handoff temporário para Codex — consolidação V1

> Documento operacional temporário, não contrato financeiro nem autorização para apagar arquivos. Remover após conclusão e aceite do Chefe. Base examinada: `develop` em `c9e043deffe5ce4d8ea20e1846a18284d283ce04` (19/09/2026). Antes de executar, conferir HEAD, PRs, issues e estado real do deploy; este documento pode ficar desatualizado.

## Pedido e limites do Chefe

Maia coordena dependências, handoffs e gates **como papel descrito em `.agents/skills/maia/SKILL.md`**, não como agente executado autonomamente. Codex faz a revisão e implementação. **Não modificar** `AGENTS.md`, `.agents/`, `.ai/`, `.claude/`, `CLAUDE.md`, `AI_MODIFICATIONS.md`, instruções dos agentes, nem configuração de desenvolvimento, branches, CI, build ou deploy. Não alterar regras financeiras por conveniência documental. Não excluir código MVC, controllers, models, views, migrations ou testes: a solicitação de limpeza refere-se aos documentos avulsos da V1, não à arquitetura funcional. Preservar `develop → build → main → publicação` conforme regras existentes; não publicar automaticamente por causa deste handoff.

## Inventário documental observado no topo de `develop`

- Entrada e continuidade: `README.md` (visão geral, setup, regras e estado), `CODEX_HANDOFF.md` (handoff datado de 13/09, referências a branches antigas e afirmação de OFX fora da publicação: **desatualizado frente ao trabalho recente**).
- Contratos/decisões que podem conter regras ainda vigentes: `FINANCIAL_PLANNING_CONTRACT.md`, `CARD_REVERSAL_CONTRACT.md`, `OFX_IMPORT_CONTRACT.md`, `OFX_CONCILIATION_DECISION.md`, `SAFE_DATA_RESET_CONTRACT.md`, `V1_ONBOARDING_CONTRACT.md`, `V1_ONBOARDING_NAVIGATION_ADDENDUM.md`.
- Planos, análises e histórico candidatos a consolidação **somente após leitura integral e rastreamento de referências**: `FINANCIAL_PLANNING_STAGES.md`, `FINANCIAL_PLANNING_REVIEW.md`, `OFX_ARCHITECTURE_REVIEW.md`, `DAILY_FINANCIAL_ENGINE_PROPOSAL.md` (proposta V2: não misturar com V1).
- `docs/BUILD_BRANCH_WORKFLOW.md` é documentação do fluxo de entrega: **preservar sem alteração**.

A existência de vários arquivos não prova redundância. Antes de remover qualquer documento, localizar referências no README, código, testes, PRs, issues e regras de IA; identificar se é contrato aprovado, histórico, proposta futura ou registro temporário. Não descartar decisões, exemplos numéricos, riscos ou rastreabilidade. Se a consolidação exigir mexer em documento de IA ou no fluxo protegido, parar e pedir decisão ao Chefe.

## Organização documental desejada (proposta para revisão, não limpeza já executada)

1. Fazer `README.md` ser a porta de entrada curta: propósito, setup existente sem mudanças, mapa de documentação canônica, estado **verificado** da V1, onde encontrar contratos e como abrir um planejamento temporário. Não transformar README em depósito de todas as regras financeiras.
2. Criar documento de planejamento **apenas quando houver decisão pendente, troca entre modelos/agentes ou tarefa complexa em andamento**; incluir objetivo, contexto, arquivos afetados, critérios de aceite, responsável por etapa, riscos, evidência e condição explícita de remoção. Preferir PR/issue para histórico concluído.
3. Consolidar documentos da V1 somente depois de uma matriz `arquivo → finalidade → referências → decisão (manter/absorver/arquivar/remover) → destino de cada informação`. Mostrar essa matriz e o diff ao Chefe antes de exclusões. Preservar contratos financeiros canônicos e a proposta V2 até decisão explícita. Não modificar o fluxo de branches ou o modo de desenvolvimento.
4. `CODEX_HANDOFF.md` está temporalmente defasado; comparar com a implementação/CI atuais antes de decidir absorver seu conteúdo útil no README ou substituir por um handoff atualizado. Não propagar suas afirmações antigas como status atual.

## Trabalho técnico pendente da V1 — Codex deve confirmar, não presumir

1. Revisar o HEAD atual de `develop`, PRs #63, #65 e #66 e issues #31, #32, #33, #49 e #50. Verificar a implementação real de agrupamento Pix na revisão OFX e o link para a compra específica, incluindo isolamento por `user_id`, soma líquida zero do par e ausência de duplicação de compra/ledger. PR #66 foi relatado com CI verde e merge em `c9e043deffe5ce4d8ea20e1846a18284d283ce04`; validar novamente no HEAD efetivo.
2. Montar a matriz financeira transversal de #50 com exemplos numéricos: saldo bancário, limite, a receber, comprometido, projeções, receitas/despesas, recebimento e pagamento parciais, cartão/antecipação/estorno/crédito, transferências, caixinhas e OFX. Comparar resultados com contratos vigentes e abrir defeitos reproduzíveis, sem criar regra de produto nova por conta própria.
3. Testar **concorrência real** de duas confirmações Pix simultâneas em conexões/processos distintos no mesmo MySQL adotado na produção; replay sequencial e SQLite não demonstram esse gate. Verificar idempotência, locks, rollback, auditoria e ausência de dupla contagem. Testar cross-user e regressões completas.
4. Revisar migrações e plano de backup/rollback; executar CI completo no último HEAD, inclusive regressão MySQL e build frontend. Não afirmar sucesso sem links para run/commit.
5. Preparar release respeitando integralmente as instruções existentes: integrar em `develop`, gerar/validar artefato em `build`, submeter a `main` pelo fluxo aprovado e conferir webhook/publicação. Smoke real desktop/mobile e aceite do Chefe são gates; não rodar `migrate:fresh`, seed de desenvolvimento nem reset destrutivo em produção.

## Handoffs de Maia e saída exigida

- Lia + Inv: ambiguidades de semântica e exemplos financeiros; levar decisões novas ao Chefe.
- Atlas + Nexo: contratos, transações, concorrência e idempotência.
- Íris: isolamento de usuários, autorização de compra e IDs.
- Nilo: correções de código estritamente necessárias; Bento: regressão, CI e smoke documentado.
- Codex: registrar no PR/issue o arquivo modificado, por quê, teste, commit e pendências; evitar criar novos `.md` permanentes para cada alteração.

**Critério de conclusão:** matriz documental revisada e aprovada antes de qualquer exclusão; V1 com testes/CI, release e smoke comprovados; pendências e decisões do Chefe explícitas. Ao final, apagar **somente este** `V1_CODEX_TEMP_PLAN.md` em PR de limpeza, depois de transferir decisões duradouras ao README/contrato canônico e registrar evidências no PR/issue. Não declarar produção validada sem observação real.