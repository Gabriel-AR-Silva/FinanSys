# Plaquinhas NFC

O módulo reúne produção, empresas interessadas, vendas e avisos em uma página autenticada do FinanSys. O menu abre `/plaquinhas-nfc`, com um painel e um sininho próprios.

## Plano de implementação

| Etapa | Responsável | Critério de aceite |
| --- | --- | --- |
| Persistência e acesso | Atlas e Nilo | Uma migration cria `nfc_records`. Cada conta acessa somente os próprios registros. |
| Cadastros e cálculos | Nilo | A ficha permite registrar lojas, unidades, plaquinhas e pagamentos. O painel calcula produção, vendas e saldos pelos registros. |
| Tela e avisos | Nilo | O menu abre o módulo no celular e no computador. A ficha da loja e o sininho abrem modais. |
| Verificação e entrega | Bento e Maia | Os testes cobrem acesso, lote inicial, pagamentos, garantia e avisos. O PR aponta para `develop`. |

## Dados e regras

- **Empresa e unidade.** A ficha aceita nome, telefone, CNPJ, observações e indicação de interesse. Cada empresa pode cadastrar e renomear unidades. Uma venda pertence a uma unidade.
- **Produção.** Cada lote registra quantidade, data e custo quando disponíveis. Registre o lote inicial pelo formulário, que abre com **60** unidades. Quantidade personalizada e custo desconhecidos podem ficar em branco no lote inicial. O total fabricado vem dos lotes.
- **Vendas.** O formulário aceita quantidades de plaquinhas normais e personalizadas. Cada plaquinha da venda recebe um ID para acompanhar a customização ou anotar um defeito. O painel mostra os dois tipos de venda e conta as plaquinhas em customização.
- **Pagamentos.** Cada entrada tem valor em centavos e forma de pagamento. Uma venda pode combinar dinheiro, Pix, débito e crédito, na hora ou depois. Cada entrada soma no arrecadado. O saldo determina se a venda está pendente, parcial ou quitada. A quantidade de plaquinhas pagas considera vendas quitadas.
- **Garantia.** A data da venda inicia os três meses de garantia. Se o mês final for mais curto, vale seu último dia. O modal mostra garantia ativa em amarelo e encerrada em azul. O sininho sinaliza vencimentos a partir de sete dias antes.
- **Avisos.** Um aviso manual tem texto, ID e estado de resolução. Pode ficar livre ou apontar para uma empresa ou plaquinha. A marcação Importante aparece em vermelho.

## Estrutura

`nfc_records` armazena o proprietário, o tipo, a chave do registro, o JSON e os timestamps. A ficha da empresa contém unidades, vendas, plaquinhas e pagamentos no JSON. Lotes e avisos têm seus próprios registros na mesma tabela. Chaves estáveis permitem reenviar operações de cadastro, e alterações em uma ficha usam transação para preservar as demais entradas.

## Primeira utilização

1. No ambiente em que o módulo será usado, execute `php artisan migrate --path=database/migrations/2026_11_11_000000_create_nfc_records_table.php`. A preparação dos assets de produção segue o [fluxo de release do FinanSys](BUILD_BRANCH_WORKFLOW.md).
2. Acesse **Plaquinhas NFC** pelo menu e registre o lote inicial de 60. Preencha o custo real quando estiver disponível.
3. Para incluir o histórico, cadastre as vendas com as datas originais e os pagamentos que já entraram. O painel usa essas datas para calcular a garantia.

## Verificação

```bash
php artisan test --compact tests/Feature/NfcWorkspaceTest.php tests/Feature/WorkspaceNavigationTest.php tests/Feature/FrontendNamedRouteConsistencyTest.php tests/Feature/FrontendRouteVerbConsistencyTest.php
npm ci
npm run build
```
