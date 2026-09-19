# Registro de modificações por IA

## 2026-09-16 — Gate de build entre develop e main

### Motivo
O ambiente de produção não possui Node/npm e o frontend Vue/Vite precisa chegar à `main` já compilado. Foi aprovado um gate permanente de build para impedir que código-fonte atualizado seja publicado com `public/build` antigo.

### Alterações
- `AGENTS.md`: fluxo Git oficial alterado para `develop -> build -> main -> webhook -> produção`.
- `docs/BUILD_BRANCH_WORKFLOW.md`: documentação operacional do gate de build.
- `.ai/rules/release-build.md`: regra durável para releases e assets Vite.
- `.ai/rules/index.md`: regra de release adicionada ao índice.

### Regra operacional
Features continuam sendo integradas em `develop`. Uma release pronta vai para `build`, onde `npm ci` e `npm run build` devem produzir e versionar `public/build`. Somente a `build` validada pode ser promovida para `main`. A hospedagem recebe o build pronto e não executa npm.

### Banco
Nenhuma migration ou alteração de banco foi realizada nesta mudança.
