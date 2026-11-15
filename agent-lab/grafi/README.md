# Grafi — especialista portátil em UI/UX e design de sistemas

Agente criado no \`agent-lab\` do FinanSys para **ser migrado a um repositório independente** junto ao Scout quando a biblioteca de agentes estiver disponível.

Não é módulo de negócio do FinanSys; o código do produto não precisa importar esta skill. Este diretório contém apenas instruções, referências e avaliações.

## Estrutura

- \`SKILL.md\` — identidade, escopo, modos, regras e contrato de resposta.
- \`protocols/ui-review.md\` — auditoria de UI, UX, acessibilidade, dados e código com evidência e severidades.
- \`references/modern-enterprise-systems.md\` — curadoria documentada de sistemas e padrões contemporâneos, incluindo ERP/analytics.
- \`references/data-visualization.md\` — decisões de KPI, gráfico, tabela, filtros e integridade de dados.
- \`references/stack-adapters.md\` — orientações cross-stack e exemplo de adaptação ao FinanSys.
- \`evals/cases.md\` — cenários adversariais e critérios de avaliação.

## Propósito

Grafi propõe e revisa interfaces web administrativas, produtos, dashboards, relatórios, formulários, tabelas, estados, interações e organização de dados. Lê código real e, quando tiver acesso, valida a interface renderizada. Responde principalmente em português. Não deve depender de Vue, Tailwind, Bootstrap nem de agentes específicos.

## Relação com Scout

Scout é pesquisador de fontes e contrapontos; Grafi é especialista que interpreta a pesquisa e a transforma em hipótese de design, recomendação, revisão ou implementação autorizada. O Grafi deve funcionar mesmo sem Scout instalado.

## Migração

1. Copiar a pasta \`agent-lab/grafi\` inteira para o repositório de agentes.
2. Copiar \`agent-lab/scout\` separadamente se desejar manter o pesquisador.
3. Revisar/remover apenas o exemplo FinanSys de \`references/stack-adapters.md\`.
4. Registrar/importar as skills usando o formato requerido pelo novo host.
5. Rodar casos em \`evals/cases.md\` em stacks e sistemas diferentes.
6. Não copiar nem modificar AGENTS.md ou arquivos do produto sem uma integração posterior explicitamente aprovada.

## Histórico

2026-11-15 — Grafi 1.0 preparado em branch de feature, sem alterações na aplicação de produção nem nos agentes existentes.
