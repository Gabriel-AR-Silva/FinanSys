# Ficha Grafi — Auditoria do módulo Plaquinhas NFC

**Data:** 15/11/2026  
**Base analisada:** `develop` do FinanSys, com correções propostas em `feature/grafi-nfc-audit-improvements`  
**Tipo:** revisão estática de UI, UX, acessibilidade, integrações de tela e contratos de servidor  
**Responsável pela perspectiva de design:** Grafi (`agent-lab/grafi/SKILL.md`)  
**Status geral:** requer correções e validação no navegador. **Não houve certificação de acessibilidade, teste visual, build nem execução da suíte local nesta auditoria.**

## Resumo executivo

O módulo possui uma base funcional ampla (painel, empresas, unidades, vendas, pagamentos, plaquinhas, fabricação, fabricantes/imagens, avisos, garantias, histórico, links compartilhados). Há tokens de tema e componentes reaproveitados. Entretanto, a revisão estática identificou problemas de usabilidade, de organização da informação e lacunas de validação que justificam uma rodada de correções antes de considerar a experiência pronta.

O risco mais importante a decidir é **escopo do link compartilhado**: a documentação e os testes existentes permitem ao portador **criar, alterar e excluir** registros NFC; isso é mais amplo que um link apenas para visualizar um painel. A UI informa o alcance, e o backend restringe o token a NFC, mas a política exata de permissão precisa ser decidida explicitamente. Não alterar silenciosamente autenticação/autorização.

## Ficha de achados por prioridade

| ID | Prioridade | Tela/fluxo | Evidência no código | Tarefa / critério de aceite | Estado |
| --- | --- | --- | --- | --- | --- |
| GRAFI-NFC-01 | P1 – Decisão de produto/segurança | Links de acesso | `routes/web.php` publica métodos POST/PATCH/DELETE em `nfc.share.*`; `NfcWorkspaceTest` confirma edição via token; `docs/PLAQUINHAS_NFC.md` autoriza alterações | Confirmar se link deve ser apenas leitura ou edição explícita. Se leitura, separar escopos no servidor, proteger upload/delete, atualizar UI/docs/testes. Nunca confiar só no esconder botões | **Aberto — requer definição** |
| GRAFI-NFC-02 | P1 – Fluxo bloqueado | Empresas sem unidades | `Index.vue` criava linhas somente com `companies.flatMap(company.units)`; remover única unidade tornava a empresa invisível na lista | Manter empresa acessível com estado "Sem unidade" e possibilidade de criar unidade | **Corrigido na branch; validação visual pendente** |
| GRAFI-NFC-03 | P1 – Interação | Exclusão de registros, imagens, avisos e links | `window.confirm()` estava em `Index.vue`, `ManufacturerDetails.vue` e `Notices.vue` | Usar fluxo contextual de confirmação no modal existente, esclarecer impacto e preservar feedback de sucesso/erro | **Corrigido na branch; validar teclado/retorno/falhas** |
| GRAFI-NFC-04 | P1 – Leitura financeira | Empresa, detalhe da venda e linha mobile | Valores e rótulos em uma sequência de spans sem agrupamento visual claro | Criar grupos de dados rotulados, com destaque semântico no saldo a receber, preservando valores e unidades | **Corrigido na branch; validação visual pendente** |
| GRAFI-NFC-05 | P1 – Cadastro | Primeira unidade da empresa | `ManageNfcRecords::storeCompany` tratava apenas `null`, não string vazia; formulário permite vazio | Deixar primeira unidade opcional e aplicar "Unidade 1" quando vazia/branca | **Corrigido na branch; regressão adicionada** |
| GRAFI-NFC-06 | P1 – Acessibilidade | Abas Empresa/Fabricação/Fabricantes; Avisos | `role=tab` sem navegação por setas ou roving tabindex; presença simultânea de `aria-pressed` e `aria-selected` | Suportar setas/Home/End, foco e seleção corretos; conferir teclado/leitor de tela | **Corrigido na branch; validação manual pendente** |
| GRAFI-NFC-07 | P2 – Dashboard | Indicadores no celular | Inicialmente três colunas forçadas com rótulos de 10px, e métricas financeiras importantes no final | Priorizar "Arrecadado/A receber/Vendidas" no primeiro grupo; melhorar texto e testar 320/375/430px; reavaliar largura de três cards | **Primeira correção feita; teste visual pendente** |
| GRAFI-NFC-08 | P2 – Densidade | Detalhes do fabricante | `ManufacturerDetails.vue` mostra 13 métricas com o mesmo peso visual | Agrupar por produção, situação comercial e custos/pendências, destacar 3–5 KPIs essenciais; manter detalhamento por expansão | **Pendente** |
| GRAFI-NFC-09 | P2 – Diagnóstico | Histórico | `Index.vue` mostra snapshots JSON completos em `<pre>` | Oferecer descrição legível do evento, campos que mudaram e acessos a detalhes técnicos; revisar privacidade do conteúdo exibido ao visitante | **Pendente** |
| GRAFI-NFC-10 | P2 – Performance | Carregamento/dashboard | `NfcDashboardQuery::forUser` carrega todos os registros da conta e agrega em memória; histórico só é limitado após carregar | Medir volume e latência; paginar histórico e considerar consultas/agregações otimizadas se dados crescerem | **Pendente — medir antes de alterar** |
| GRAFI-NFC-11 | P2 – Formulários | Cadastro de venda e lote | `Editor.vue` mostra erros em lista no topo, com fluxo de dinheiro/quantidade em múltiplas seções | Associar erro ao campo, revisar totais e limites em cada estado; testar teclado e navegação depois de falha | **Pendente** |
| GRAFI-NFC-12 | P2 – Visual/contraste | Todas as telas NFC | `nfc.css` usa tokens; partes dos componentes e badges necessitam verificação de contraste real | Validar tema claro/escuro, foco, zoom 200–400%, reduced motion e reflow | **Pendente — requer navegador** |
| GRAFI-NFC-13 | P2 – Responsividade | Lista de unidades, tabelas, galeria e modais | `Index.vue` usa tabela desktop com `min-w-[780px]` e cards mobile; `ManufacturerDetails.vue` usa galeria e prévia na mesma janela | Verificar scroll horizontal limitado à tabela, acesso a ações, imagens em proporções diferentes, tamanho de toque, teclado móvel e modais longos | **Pendente — requer navegador** |
| GRAFI-NFC-14 | P2 – Acessibilidade/dados | Indicadores e gráficos | KPI já apresenta valores, mas não há distinção visual de blocos financeiros e operacionais além da ordem | Considerar cabeçalhos por área, unidades/definições e comparações temporalmente válidas; **não introduzir gráficos decorativos** | **Pendente** |
| GRAFI-NFC-15 | P2 – Qualidade de entrega | Testes frontend/build | Há feature tests de rotas, dados, pagamentos e upload; ausência de validação visual e E2E reproduzível na auditoria | Executar `php artisan test` selecionado, `npm ci && npm run build`, testar desktop/mobile, visitante e dono em dois temas | **Pendente** |

P0 não foi confirmado apenas por leitura; isso **não** é garantia de ausência de bloqueios no navegador. A severidade pode ser atualizada após testes.

## Cobertura funcional revisada estaticamente

| Área | Arquivos principais | Fluxos/estados para validar |
| --- | --- | --- |
| Dashboard | `Index.vue`, `NfcDashboardQuery.php` | KPI, dados ausentes, custo desconhecido, defeitos, saldo recebido/pendente, filtros |
| Empresas/unidades | `Index.vue`, `CompanyDetails.vue`, `Editor.vue`, `ManageNfcRecords.php` | CRUD, empresa sem unidades, filtro por interesse, busca, vínculo a lote |
| Vendas/pagamentos/plaquinhas | `Editor.vue`, `CompanyDetails.vue`, `NfcController.php` | Quantidades, pagamentos parciais/mistos, exclusão, limite total, defeitos, customização |
| Fabricação | `Index.vue`, `Editor.vue`, `ManageNfcRecords.php` | Lote inicial, status, quantidades, custo desconhecido, vínculos e exclusão protegida |
| Fabricantes/galeria | `ManufacturerDetails.vue`, `NfcManufacturerController.php` | CRUD, upload até 10 imagens, tipo/tamanho, preview/download privado, exclusão |
| Avisos/garantias | `Notices.vue`, `NfcDashboardQuery.php` | Pendente, resolvido, reaberto, garantia próxima, vínculo à empresa/plaquinha |
| Histórico | `Index.vue`, `ManageNfcRecords.php` | Eventos, antes/depois, leitura humana, privacidade |
| Compartilhamento | `NfcSharedLayout.vue`, `NfcShareLinkController.php`, middleware | Geração, cópia, prazo, revogação, acesso isolado, autorização de leitura/escrita |
| Responsividade, temas, teclado | `nfc.css`, `Index.vue`, componentes comuns | 320/375/768/1280px, foco, zoom, contraste, modais, scroll e toque |

## Alterações iniciadas nesta branch

- Backend: campo opcional `unit_name` vazio/branco agora gera `Unidade 1`.
- Frontend: empresas sem unidades recebem linha de acesso, sem sumir da tabela ou dos cards.
- Frontend: resumo de venda e empresa em grupos rotulados com valores tabulares; cartões de unidades mobile reorganizados.
- Frontend: confirmação contextual de exclusões e revogações substitui `window.confirm()` nos fluxos NFC examinados; permanece obrigatório validar foco, falhas e operações concorrentes.
- Frontend: KPIs financeiros prioritários no início e tipografia mobile melhorada.
- Frontend: navegação por teclado nas abas principais e nas categorias de avisos.
- Testes: regressão para primeira unidade sem nome e existência da empresa após exclusão da última unidade.

## Próxima sequência recomendada

1. **P1/decisão:** definir permissão dos links compartilhados (visualização versus edição); isolar leitura/escrita no servidor, se necessário.
2. **QA da primeira rodada:** testar manualmente fluxos de confirmação, abas, vazio, upload/galeria, dashboards mobile e modo escuro; rodar regressões.
3. **P2/estrutura:** agrupar métricas de fabricante e reduzir densidade de histórico e formulários.
4. **P2/desempenho:** medir volume de dados e paginação de histórico.
5. **Gate de merge:** PR de correções para `develop` com testes, evidências de UI e revisão de riscos. `build` e `main` somente no fluxo de release aprovado.

## Comandos de validação (ainda não executados nesta auditoria)

```bash
php artisan test --compact tests/Feature/NfcWorkspaceTest.php tests/Feature/NfcManufacturerTest.php tests/Feature/NfcFrontendRouteContractTest.php
npm ci
npm run build
```

Executar pelo menos: criação/edição/exclusão de empresa, unidade, venda, pagamento, plaquinha, lote, aviso, fabricante e imagem; navegação em modal; sininho; histórico; geração/revogação de compartilhamento; usuário autenticado versus link compartilhado. Guardar evidências por viewport e tema.

**Regra de conclusão:** não declarar o módulo integralmente auditado **visualmente** sem renderização e testes de interação. Esta ficha é uma auditoria **estática abrangente dos arquivos principais e dos fluxos mapeados**, com primeira rodada de melhorias em código.
