# Fluxo de build e publicação

Este arquivo registra a regra operacional aprovada pelo Chefe para promoção do FinanSys até produção.

## Branches permanentes

- `develop`: integração do código-fonte aprovado durante o desenvolvimento. Features, correções e hardening continuam nascendo de `develop` e retornando para `develop` por PR.
- `build`: branch de preparação de release. Recebe `develop`, executa/recebe o build de produção e versiona `public/build` junto com o código que o gerou.
- `main`: produção. Recebe somente a release preparada em `build`. O webhook de hospedagem continua observando `main` e publica automaticamente quando ela é atualizada.

## Fluxo oficial

`develop` -> `build` -> `main` -> webhook -> produção

1. Desenvolvimento e testes acontecem em branches derivadas de `develop` e são integrados em `develop`.
2. Quando uma versão estiver pronta para publicação, `develop` é promovida para `build`.
3. Em `build`, usar Node compatível com o projeto, executar `npm ci` e `npm run build` e validar `public/build/manifest.json` e os assets referenciados.
4. O diretório `public/build` deve ser commitado na branch `build`. O commit de build deve corresponder exatamente ao código-fonte da mesma release.
5. Somente depois do build e das validações, abrir/promover `build` para `main`.
6. Não executar build na hospedagem compartilhada. Produção recebe os assets compilados e versionados pela `main`.
7. Migrations, quando existirem, continuam sendo uma etapa separada e explícita de deploy. Nunca usar `migrate:fresh` em produção.

## Regra de segurança da release

Não promover `develop` diretamente para `main`. A branch `build` é o gate obrigatório de empacotamento entre desenvolvimento e produção. Se o build falhar ou o manifest referenciar arquivos inexistentes, a release não deve seguir para `main`.
