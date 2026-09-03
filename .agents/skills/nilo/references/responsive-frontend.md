# Responsividade no frontend do FinanSys

Pesquisa revisada em 2026-09-03. Use estes critérios em mudanças Vue/Inertia/Tailwind; eles não autorizam redesenhar telas fora do escopo pedido pelo Chefe.

## Decisões de implementação

- Comece em uma coluna e adicione estrutura quando o conteúdo exigir, sem breakpoints por modelo de aparelho.
- Use breakpoints de viewport no shell; componentes reutilizáveis devem responder ao espaço do contêiner quando a versão instalada oferecer suporte adequado.
- Preserve a ordem lógica do DOM, foco visível, nomes acessíveis e acesso por teclado. Hover nunca pode ser o único acesso a uma ação.
- Não dependa somente de verde/vermelho para comunicar valores ou tendências; combine texto, sinal ou ícone.
- Tabelas e gráficos podem ter rolagem interna deliberada. A página não deve ganhar rolagem horizontal.
- Em telas estreitas, priorize informação ou use cartões quando isso preservar a semântica. Não reduza tudo até ficar ilegível.
- Modais devem caber no viewport, rolar internamente, fechar por Escape e devolver o foco ao acionador.
- Não use `overflow-x-hidden` no shell para mascarar largura fixa, nem desative zoom. Prefira CSS a lógica baseada em `window.innerWidth`.

## Gate proporcional

Valide no Edge em 360×800, 390×844, 768×1024, 1280×720 e 1440×900, incluindo zoom de 125%, 150% e 200%. Em cada tela alterada, confirme:

- ausência de corte, sobreposição e overflow horizontal da página;
- ações essenciais disponíveis sem hover, com alvos de pelo menos 24×24 CSS px ou espaçamento equivalente;
- conteúdo longo, valores grandes ou negativos, vazio, erro e carregamento sem quebra;
- navegação por teclado em ordem lógica e foco não encoberto por elementos fixos;
- sidebar/drawer, modal, formulário, tabela e gráfico funcionando nos breakpoints em que aparecem;
- console sem erros durante redimensionamento e navegação.

Emulação de viewport é triagem, não evidência final para mobile. Antes de publicar uma correção móvel, valide ao menos um aparelho físico para cobrir teclado virtual, toque, barras dinâmicas do navegador, `dvh` e rolagem real.

Execute `npm run build`, os testes afetados e a suíte completa antes da integração. Enquanto o projeto não tiver ferramenta E2E, registre a verificação manual com viewport, zoom e evidência visual; Bento revisa o aceite.

## Fontes primárias

- [W3C WCAG 2.2 — Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html)
- [W3C WCAG 2.2 — Resize Text](https://www.w3.org/WAI/WCAG22/Understanding/resize-text.html)
- [W3C WCAG 2.2 — Target Size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html)
- [W3C WCAG — Use of Color](https://www.w3.org/WAI/WCAG22/Understanding/use-of-color.html)
- [MDN — Responsive design](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/CSS_layout/Responsive_Design)
- [Vue — Accessibility](https://vuejs.org/guide/best-practices/accessibility.html)
- [Tailwind CSS — Responsive design](https://tailwindcss.com/docs/responsive-design)
