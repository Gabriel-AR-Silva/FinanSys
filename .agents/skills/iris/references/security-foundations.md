# Fundamentos de segurança aplicáveis ao FinanSys

Pesquisa revisada em 2026-09-01. Use como apoio técnico, não como autorização
para mudar contratos de produto ou arquitetura.

## Recuperação de senha

- A resposta pública deve ser equivalente para e-mail existente e inexistente,
  inclusive evitando diferenças temporais perceptíveis.
- Limite requisições e mantenha token aleatório, expirável e de uso único.
- Falhas operacionais pertencem a logs e métricas; não devem revelar a conta.
- Agora: resposta neutra, throttle e testes para e-mail existente/inexistente.
- Depois: acompanhar abuso e entrega no provedor real. CAPTCHA ou MFA exigem
  risco concreto, não entram automaticamente no MVP.

Evidências: [OWASP Forgot Password](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)
e [Laravel Password facade](https://api.laravel.com/docs/12.x/Illuminate/Support/Facades/Password.html).

## Verificação de e-mail

Adote uma política única. Para o MVP local, sem cadastro e com usuário criado
por seeder, o fluxo pode ser removido. Se a verificação for requisito, o modelo
deve implementar `MustVerifyEmail` e todas as rotas financeiras devem exigir
`verified`; perfil, verificação e logout permanecem acessíveis. Alterar o e-mail
deve invalidar a verificação.

O estado atual é incoerente: existem rotas e middleware de verificação, mas
`User` não implementa o contrato e somente o Dashboard usa `verified`. Esta é
uma decisão pendente do Chefe, não uma correção automática.

Evidências: [Laravel middleware](https://laravel.com/framework/docs/12.x/middleware)
e [MustVerifyEmail](https://api.laravel.com/docs/12.x/Illuminate/Auth/MustVerifyEmail.html).

## Isolamento por usuário

- Resolva qualquer ID recebido dentro do conjunto pertencente ao usuário.
- `morphMap`, enum e UUID não substituem autorização por objeto.
- Use o `ReferenceResolver` como entrada única para referências polimórficas e
  valide novamente na action/transação que grava.
- Aplique escopo de usuário em leitura, criação, alteração, exclusão,
  restauração e transferência; objeto inexistente e alheio não devem ser
  distinguíveis externamente.
- Teste matriz usuário A/B para account e pocket, inclusive tipo adulterado,
  soft delete e combinação pocket/conta de proprietários diferentes.

RLS, schema ou chave criptográfica por usuário são prematuros para este MVP.

Evidências: [OWASP BOLA](https://owasp.org/API-Security/editions/2023/en/0xa1-broken-object-level-authorization/),
[OWASP IDOR](https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html)
e [OWASP Multi-Tenant Security](https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html).
