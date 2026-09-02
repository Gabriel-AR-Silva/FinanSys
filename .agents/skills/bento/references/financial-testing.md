# Estratégia de testes financeiros do FinanSys

Pesquisa revisada em 2026-09-01. Busque confiança proporcional ao risco, sem
duplicar em navegador o que um teste Feature comprova melhor.

## Camadas

- Unitário: cálculos monetários puros, enums e regras sem infraestrutura.
- Feature/HTTP Laravel: maioria da suíte; validação, autorização, banco,
  transações, rotas e props Inertia.
- Componente Vue: estado relevante isolado, como gráfico, toast e modal.
- E2E: poucas jornadas críticas que somente o navegador comprova.

## Invariantes prioritárias

- Dinheiro permanece exato e não é calculado com `float`.
- Saldo é a soma assinada dos lançamentos válidos; despesa não duplica sinal.
- Transferência cria entrada e saída iguais na mesma transação.
- Falha não deixa lançamento órfão.
- Toda referência pertence ao usuário autenticado.
- Mesma chave idempotente produz um efeito; conteúdo diferente é rejeitado.
- Exclusão e restauração preservam lançamentos, saldo, lote e prazo.
- Concorrência é testada no mecanismo de banco pretendido para produção.

## Gate para E2E

Adote Playwright quando o primeiro CRUD financeiro de caixinhas ou lançamentos
estiver funcional. Comece por login, conta com toast, validação, exclusão e
restauração, receita/despesa, transferência, gráfico e viewport móvel.

Para modais, valide foco inicial, Tab/Shift+Tab, Escape, retorno ao acionador,
nome acessível e bloqueio do conteúdo ao fundo.

## Evitar fragilidade

- Selecione por papel, nome e label; `data-testid` é último recurso.
- Não selecione por classes Tailwind, posição ou árvore DOM profunda.
- Não use sleeps; prefira assertions com espera automática.
- Cada teste cria dados por factory e não depende de ordem ou seeder local.
- Congele o relógio para prazos e períodos.
- Verifique resultado do domínio, não detalhes internos.
- Em falha E2E, preserve trace, screenshot ou vídeo.

## Checklist por funcionalidade

- Caso feliz persiste o resultado correto; entrada inválida não altera dados.
- Visitante e segundo usuário são bloqueados em todos os verbos aplicáveis.
- Operação relevante é atômica, idempotente e mantém o saldo derivado.
- Rota aceita somente o verbo esperado e o controle visível chega até ela.
- Modal funciona por mouse e teclado; feedback corresponde ao commit.
- Jornada essencial funciona em desktop e mobile.
- O teste passa isolado e com a suíte.

Fontes: [Laravel Testing](https://laravel.com/docs/12.x/testing),
[Laravel HTTP Tests](https://laravel.com/docs/12.x/http-tests),
[Laravel Database Testing](https://laravel.com/docs/12.x/database-testing),
[Inertia Testing](https://inertiajs.com/docs/v2/testing),
[Playwright Locators](https://playwright.dev/docs/locators),
[Playwright Auto-waiting](https://playwright.dev/docs/actionability),
[Playwright Isolation](https://playwright.dev/docs/browser-contexts) e
[OWASP Authorization Testing](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Regression_Testing_Cheat_Sheet.html).
