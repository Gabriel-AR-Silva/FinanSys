# Handoff do desenvolvimento — FinanSys

Atualizado em: 2026-09-13  
Branch de origem: `copilot_mod_v1`  
Branch de continuidade: `codex/copilot-mod-v1-hardening`  
Base original: `main@182c06003663067d8b2292fb83a67ff8dd76f29a`  
Commit do Copilot analisado: `f7c192bed83cdf7bbfaafdffb5094672e2f3c4c1`

## Objetivo deste arquivo

Este é o ponto único de continuidade para o próximo agente de desenvolvimento. Antes de continuar, leia também `AGENTS.md`, `.ai/rules/index.md`, `FINANCIAL_PLANNING_CONTRACT.md` e `FINANCIAL_PLANNING_STAGES.md`.

A ordem de autoridade é: contrato/regras aprovadas, código e migrations atuais, testes/evidências, instruções dos agentes e, por fim, este handoff. Este arquivo registra continuidade; não substitui o contrato.

## Base funcional recebida do Copilot

O commit `f7c192b` expandiu o FinanSys de um ledger financeiro básico para o primeiro domínio de planejamento financeiro, incluindo:

- configuração financeira mensal, proteção e essenciais;
- previsões de recebimento com recorrência, parcial, residual, excedente, cancelamento e vínculo explícito;
- cartões, compras parceladas, parcelas e pagamentos;
- reembolsos ligados à despesa original;
- visões atual e projetada;
- fechamento diário, reconstrução, revisões e proveniência;
- alertas financeiros internos deduplicados;
- telas Inertia para configuração, previsões, cartões, avaliações e alertas;
- contratos P1–P6 que definem o fechamento da publicação.

WhatsApp e importação OFX permanecem intencionalmente fora desta publicação.

## Hardening realizado na branch Codex

1. **Fronteira mensal no fuso da aplicação**

   `FinancialPlanningOverviewQuery` passou a manter as fronteiras mensais no fuso configurado em vez de deslocar indevidamente o começo/fim do mês.

2. **Alertas sincronizados com mutações financeiras relevantes**

   Compra parcelada, pagamento de cartão, previsões, vínculos, reembolsos, estornos manuais e exclusões/restaurações cobertas atualizam os alertas no mesmo fluxo transacional aplicável. Replay idempotente não repete efeito.

3. **Reativação correta de alertas recuperados**

   Reincidência adversa limpa `recovered_at`, preservando pior situação e déficit já observado.

4. **Encargos de cartão entregues**

   Juros/multas confirmados são obrigações próprias, entram no planejamento sem duplicar principal e só são liquidados quando explicitamente selecionados. O fluxo possui controller/request/UI e integração com pagamento.

5. **Seleção de encargos endurecida**

   Commit `b95ce50` adicionou regressões para encargo pertencente a outro cartão do mesmo usuário e seleção duplicada. Ambos abortam sem pagamento, alocação ou movimento parcial. Encargo de outro usuário já possuía cobertura.

6. **CI e artefatos de frontend**

   `.github/workflows/ci.yml` executa Composer, Pint, PHPUnit, npm, build Vite, validação do manifest e upload de `public/build`. O build versionado foi restaurado porque o fluxo atual de hospedagem depende desses artefatos.

7. **Concorrência transferência x estrutura parcialmente endurecida**

   `TransferFunds` agora adquire `lockForUpdate()` no usuário antes de reler idempotência, bloquear referências, calcular saldo e gravar as duas pernas. `DeleteAccount` e `DeletePocket` já usam o mesmo lock. `RestoreAccount` também passou a bloquear o usuário e a restaurar relações bloqueadas em ordem determinística.

   `RestorePocket` também passou a usar o lock de usuário, bloquear conta, caixinha e lançamentos restaurados em ordem estável e aceitar replay sem repetir auditoria. Isso reduz a janela de corrida identificada pelo Nexo, mas **não fecha o gate de concorrência**: SQLite não comprova comportamento de locks/deadlocks do banco de produção.

8. **README sincronizado**

   `README.md` deixou de afirmar que categorias e fluxo HTTP de transferências não existem. Agora descreve o estado real, os limites do CI e aponta para contrato/handoff.

9. **Roteiro de etapas corrigido**

   `FINANCIAL_PLANNING_STAGES.md` agora reconhece encargos como entregues, registra o hardening de concorrência já feito e consolida a política P5 de preservar histórico/auditoria sem exclusão automática nesta publicação.

10. **Antecipação de parcelas entregue**

   Parcelas pendentes de meses futuros podem ser selecionadas explicitamente. O domínio preserva bruto liberado, desconto proporcional, líquido pago e vencimento original por alocação; o caixa recebe uma única saída líquida e o planejamento desloca somente esse líquido ao mês atual, removendo o bruto dos meses futuros. A interface mostra a distribuição antes da confirmação e o backend rejeita prévia obsoleta.

## Evidência recente

Workflow **FinanSys CI** run #43, commit `c2b2ca0c598c7f0a3b42b9689f380077404602f0`, concluiu com sucesso em 2026-09-13: Pint, 371 testes PHPUnit com 1.996 asserções, frontend/build e validação do manifest passaram.

O commit documental seguinte deve manter o mesmo gate verde antes de qualquer merge.

## Pendências reais

Não confundir com itens já entregues:

1. **E3 — completar cartões:** estorno de compra separando pendente de parte já paga; crédito do cartão e sua aplicação a outra obrigação somente por associação explícita; contratos HTTP, UI e testes desses dois fluxos.
2. **Concorrência real:** executar cenários concorrentes no mesmo mecanismo de banco adotado em produção. SQLite não é evidência suficiente.
3. **E5:** reexecutar a jornada contratual integrada após o fechamento de E3 e revisar isolamento transversal dos consumidores.
4. **Aceite visual:** Microsoft Edge desktop/mobile real, incluindo teclado, foco, zoom, valores longos, modais, previsões, cartões, histórico e alertas.
5. **Deploy:** ensaiar migrations no banco compatível com produção, backup/retorno, preservação da `APP_KEY`, OAuth, HTTPS, filas, scheduler e smoke test.
6. **Política contábil de estorno:** registrar decisão final de competência/data antes de liberar o novo estorno de compra.
7. **Proteção da branch:** configuração administrativa continua externa ao código e deve exigir CI verde antes do merge.

## Ordem recomendada para concluir

1. Registrar a política contábil da data do estorno e fechar E3 em dois incrementos verificáveis: estorno; crédito explícito.
2. Integrar os novos fatos em E5/alertas sem dupla contagem.
3. Testar concorrência no banco escolhido para produção.
4. Rodar suíte completa, Pint, build e revisão do diff.
5. Fazer aceite visual Edge desktop/mobile.
6. Ensaiar migration/deploy e executar smoke test.
7. Só então abrir/mesclar PR para a branch de integração; não fazer push direto na `main`.

## Comandos de verificação local

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
vendor/bin/pint --test
php artisan test --compact
npm run build
```

Após `npm run build`, confirmar que os arquivos citados pelo `public/build/manifest.json` existem e que os artefatos necessários ao deploy estão versionados conforme o fluxo atual.

## Regras que não devem ser quebradas

- valores financeiros permanecem decimais exatos, nunca `float`;
- todo dado financeiro permanece isolado por `user_id`;
- operação mutável usa idempotência e rejeita a mesma chave com parâmetros diferentes;
- escrita financeira, auditoria e efeitos internos obrigatórios permanecem atômicos;
- transferência interna não vira receita ou despesa de planejamento;
- previsão aceita vários recebimentos, mas uma receita real pertence integralmente a no máximo uma previsão ativa;
- reembolso antigo afeta patrimônio, não renda sustentável do mês;
- avaliações reconstruídas não fingem ter sido apresentadas ao usuário;
- crédito de cartão não é renda nem caixa;
- WhatsApp e OFX não bloqueiam a publicação atual.
