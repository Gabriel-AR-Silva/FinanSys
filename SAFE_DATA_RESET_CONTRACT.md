# Contrato de limpeza segura de dados de uso — FinanSys

## Estado

- **Status:** implementado e revisado contra V1/V2 em 2026-09-23; validação automatizada permanece obrigatória a cada ampliação do schema.
- **Objetivo:** permitir limpar dados gerados pelo uso real/testes do sistema sem destruir identidade, estrutura básica nem configurações essenciais.
- **Entrada UI:** Configurações avançadas / Danger Zone.
- **Risco:** operação destrutiva e irreversível; exige confirmação forte e execução transacional.

## Princípio

O reset não é `truncate`, `migrate:fresh` nem limpeza global do banco.

Ele atua somente nos dados pertencentes ao usuário autenticado e deve preservar a estrutura necessária para continuar usando o FinanSys imediatamente depois.

## Preservar

Primeiro incremento preserva:

- usuário, autenticação e identidade social;
- categorias existentes;
- contas;
- caixinhas;
- cartões;
- configurações financeiras estruturais;
- configurações essenciais;
- preferências e dados necessários ao onboarding já concluído;
- dados de sistema que não representem uso financeiro real.

Enquanto `Category` não possuir uma origem explícita (`system`, `seed`, `user` etc.), o reset **não exclui categorias pelo nome ou por heurística**.

## Limpar

O reset deve remover, de forma coordenada e na ordem exigida pelas dependências, fatos financeiros/operacionais do usuário, incluindo quando existentes:

- lançamentos de receita/despesa;
- transferências;
- reembolsos;
- previsões/recebimentos operacionais que representem uso real;
- compras de cartão;
- parcelas;
- pagamentos;
- encargos;
- antecipações;
- estornos;
- créditos de cartão;
- aplicações de crédito;
- importações OFX;
- itens de importação OFX;
- vínculos derivados de importação;
- alertas financeiros derivados;
- avaliações/fechamentos/revisões derivados dos fatos removidos;
- demais registros auxiliares cuja única origem sejam fatos que serão apagados.

A matriz vigente deve ser revista sempre que uma migration adicionar novo dado operacional por usuário.

### Matriz vigente de limpeza

| Grupo | Ação | Dependência/ordem |
| --- | --- | --- |
| Identidade, autenticação e identidade social | preservar | base do usuário |
| Categorias, contas, caixinhas e cartões | preservar | estrutura reutilizável |
| Configuração financeira mensal e essenciais | preservar | configuração estrutural |
| Metas financeiras | remover | antes de qualquer futura remoção de caixinhas; atualmente caixinhas são preservadas |
| Check-ins financeiros diários | remover | antes das versões de orçamento referenciadas |
| Versões de orçamento diário | remover | depois dos check-ins |
| Compromissos futuros e pagamentos | remover | pagamentos antes dos compromissos e antes dos lançamentos associados |
| OFX e itens | remover | itens antes da importação |
| Operações de cartão | remover | alocações/créditos/estornos antes dos agregados-base |
| Vínculos de previsões, reembolsos e lançamentos | remover | filhos antes dos lançamentos |
| Alertas e avaliações derivadas | remover | antes de concluir o reset |
| Auditoria dos fatos removidos | remover | preservar apenas o evento mínimo do reset |

## Auditoria e snapshots

Não basta apagar os registros financeiros se a auditoria mantiver snapshots contendo os mesmos dados.

O reset deve:

1. remover ou anonimizar snapshots financeiros referentes aos registros eliminados, conforme a arquitetura vigente da auditoria;
2. não deixar conteúdo financeiro removido recuperável pela UI comum;
3. criar um novo evento de auditoria mínimo indicando que o usuário executou um reset de dados operacionais, sem regravar os valores apagados.

A política exata deve respeitar criptografia de snapshots e integridade referencial existentes.

## Segurança

- rota autenticada;
- reautenticação/senha quando suportado pelo fluxo atual;
- confirmação forte em três fatores de intenção: senha atual, código aleatório de 10 caracteres com validade de 5 minutos e confirmação explícita do controle deslizante;
- sem endpoint GET destrutivo;
- proteção CSRF;
- rate limit;
- isolamento por `user_id`;
- transação de banco;
- rollback completo em falha;
- nunca aceitar `user_id` arbitrário enviado pelo frontend;
- resposta amigável sem revelar estrutura interna.

## UI

Em **Configurações avançadas**:

### Limpar dados de uso

Texto sugerido:

> Remove movimentações e demais dados financeiros gerados durante o uso do sistema. Sua conta, categorias, contas bancárias, cartões e configurações básicas serão preservados.

A interface deve mostrar de forma curta o que será preservado e o que será apagado antes da confirmação.

## Execução

A implementação deve preferir uma Action dedicada, por exemplo conceitualmente `ResetOperationalFinancialData`, responsável por:

1. bloquear/serializar a operação por usuário quando necessário;
2. resolver dependências;
3. remover dados operacionais em ordem segura;
4. tratar soft-deleted relacionados;
5. limpar artefatos derivados;
6. registrar a auditoria do reset;
7. confirmar commit somente ao final.

Não espalhar `delete()` por controller.

## Relação com OFX

Importações OFX e seus itens são dados operacionais e devem ser removidos pelo reset.

Depois do reset:

- o mesmo OFX pode ser importado novamente porque as chaves de deduplicação daquele usuário foram removidas junto com a importação;
- nenhuma movimentação antiga deve reaparecer apenas por cache/avaliação/alerta residual;
- configurações estruturais permanecem disponíveis para o novo teste.

## Testes obrigatórios

- remove dados operacionais do usuário A;
- preserva estruturas do usuário A;
- não altera nada do usuário B;
- preserva categorias;
- preserva contas/caixinhas/cartões/configuração;
- remove OFX/import items;
- remove derivados/alertas/avaliações aplicáveis;
- trata soft deletes;
- rollback completo em falha simulada;
- confirmação inválida não escreve nada;
- replay seguro/idempotência definida para dupla submissão;
- UI não permite disparo acidental.

## Critério de conclusão

O reset só pode ser considerado seguro quando houver uma matriz explícita `tabela/modelo -> preservar/remover -> ordem/dependência`, revisada contra o schema atual e coberta por testes.

## Registro

- **2026-09-13:** Chefe solicitou botão em configurações avançadas para limpar dados de uso real sem remover categorias/dados essenciais; arquitetura acima consolidada antes da implementação.
