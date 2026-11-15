# Grafi — Adaptadores de stack (opcionais)

O Grafi é independente de biblioteca. Um adaptador é **contexto local**, não obrigação global.

## Descoberta antes de sugerir CSS/componentes

1. Inspecionar package.json, lockfile, bundler, CSS imports e diretórios de componentes; confirmar versão efetivamente instalada, não só range.
2. Identificar CSS global, tokens, Tailwind config, Bootstrap CSS/JS real, CSS Modules/Sass, UI kit, componentes internos e regras.
3. Escolher solução que não conflite com a stack e seja acessível.
4. Preferir componentes existentes e padrões aceitos pelo projeto.
5. Se solução exigir nova dependência, apresentar trade-off, licença/manutenção e solicitar autorização necessária.

### Tailwind CSS
- Confirmar v3/v4, abordagem de dark mode, prefixo, plugins e tokens.
- Checar conflitos entre utilitários e CSS específico. Grids devem atender conteúdo, não virar fórmula fixa.
- Não inventar utilitários de versão incorreta. Evitar classes geradas dinamicamente que o compilador não detecta.

### Bootstrap
- Confirmar que o Bootstrap de UI foi importado e sua versão. Um arquivo chamado \`bootstrap.js\` em projetos Laravel pode ser apenas inicializador de Axios e **não prova a presença do Bootstrap CSS**.
- Não misturar classes v4/v5 sem verificar. Priorizar grid, utilitários, componentes, variáveis e práticas acessíveis da versão instalada.
- Componentes JS exigem verificar lifecycle e integração com Vue/React; não duplicar controle DOM e estado reativo sem motivo.

### CSS puro/Sass
- Estabelecer tokens semânticos, componentes reutilizáveis, convenção de nomes e escopo de seletores.
- Usar CSS Grid/Flexbox e media/container queries onde suportadas pelo ambiente.
- Evitar valores mágicos e especificidade agressiva; documentar exceções.

### Vue/React/Blade/outros
- Identificar ownership do estado, semântica e reatividade. Não impôr Headless UI se não existir.
- Usar HTML nativo (button, table, details, dialog) quando adequadamente suportado e compatível com a experiência.
- Testar teclas, foco, interações e transições, não confiar em screenshots.

## Adaptador de exemplo: FinanSys (não aplicar a outros sistemas)

Esta seção exemplifica como conectar o Grafi a um projeto; **não faz parte de sua identidade portátil**.

Levantamento do repositório FinanSys em 2026-11-15 (branch develop, quando consultada):
- Laravel, Inertia, Vue 3, Tailwind CSS v3 declarado em package.json e CSS próprio. Há dependência \`@tailwindcss/vite\` v4 declarada em paralelo: inspecionar lockfile/build antes de assumir interoperabilidade ou trocar sintaxe.
- \`resources/js/bootstrap.js\` importa Axios; não demonstra Bootstrap CSS de interface.
- \`resources/css/app.css\` e \`dark-contrast.css\` mantêm tokens globais de cores e adaptações a tema escuro.
- Regra local \`.ai/rules/js.md\`: preferir tokens \`--fs-*\` para superfície, texto, bordas e estados.
- \`resources/js/Components/Modal.vue\` implementa dialog e transições; \`ToastHost.vue\` já fornece mensagens de sucesso, erro e aviso; \`SmartTable.vue\` fornece tabela com busca, sort e paginação; \`WorkspaceEmptyState.vue\` tem estado vazio.
- \`resources/js/Pages/Nfc/nfc.css\` apresenta componentes com tokens semânticos.

Conclusão: ao revisar o FinanSys, procurar primeiro pelos componentes locais e tokens. Algumas partes usam cores fixas e estilos herdados; apontar falhas pontuais com evidência e sem aplicar correção global indiscriminada. Se a skill for migrada para outro repo, remover este adaptador ou substituí-lo, não transformar essas escolhas em universais.

## Fontes de documentação conforme stack

- https://tailwindcss.com/docs — conferir versão
- https://getbootstrap.com/docs/ — conferir versão
- https://developer.mozilla.org/en-US/docs/Web/CSS — CSS de plataforma
- https://vuejs.org/guide/introduction.html — Vue
- https://www.w3.org/WAI/ARIA/apg/ — comportamentos acessíveis
- https://www.w3.org/TR/WCAG22/ — critérios de acessibilidade

Referências a ferramentas são orientação, não autorização para instalar pacotes.
