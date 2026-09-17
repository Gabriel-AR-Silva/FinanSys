# Release build gate

Aplica-se ao fluxo de branches, `.github/workflows/**`, `public/build/**` e promoção de releases.

- Fluxo obrigatório: `develop` -> `build` -> `main` -> webhook -> produção.
- `develop` não deve ser promovida diretamente para `main`.
- `build` deve conter o mesmo código-fonte da release e o `public/build` produzido por `npm ci` + `npm run build`.
- Validar o manifest e a existência dos assets antes de promover `build` para `main`.
- A hospedagem compartilhada não é ambiente de compilação Node; a `main` deve chegar à produção com os assets compilados.
- Migrations permanecem separadas do build e nunca se usa `migrate:fresh` em produção.
