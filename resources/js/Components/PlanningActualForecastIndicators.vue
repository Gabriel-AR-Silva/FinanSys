<script setup>
import { computed } from 'vue';

const props = defineProps({ planning: { type: Object, required: true } });
const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => money.format(Number(value));
const indicators = computed(() => [
    {
        title: 'Recebimentos do mês',
        confirmedLabel: 'Recebido',
        confirmed: props.planning.income.received,
        forecastLabel: 'Ainda a receber (previsto)',
        forecast: props.planning.income.pending,
        explanation: 'A previsão mostra somente o saldo ainda esperado. Valores previstos não estão disponíveis na conta; recebimentos parciais não são contados duas vezes.',
    },
    {
        title: 'Despesas variáveis no planejamento V1',
        confirmedLabel: 'Computadas até agora',
        confirmed: props.planning.variable.realized,
        forecastLabel: 'Estimativa variável do mês inteiro',
        forecast: props.planning.variable.projected,
        explanation: 'A estimativa já inclui o valor computado: não some os dois. Pode incluir parcelas já pagas, despesas extraordinárias e itens sem classificação. Fixos e compromissos anteriores entram em outras parcelas do planejamento; este bloco NÃO é o total de contas previstas nem o gasto diário confirmado da V2.',
    },
]);
</script>

<template>
    <section v-if="planning.configured" class="mt-4 grid gap-3 lg:grid-cols-2" aria-label="Recebimentos e despesas: realizado e previsto no mês">
        <article v-for="item in indicators" :key="item.title" class="min-w-0 rounded-xl border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-slate-900">{{ item.title }}</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg bg-emerald-50 p-3">
                    <p class="text-xs font-medium text-emerald-800">{{ item.confirmedLabel }}</p>
                    <p class="mt-1 break-words text-lg font-semibold text-emerald-950">{{ formatMoney(item.confirmed) }}</p>
                </div>
                <div class="rounded-lg bg-indigo-50 p-3">
                    <p class="text-xs font-medium text-indigo-800">{{ item.forecastLabel }}</p>
                    <p class="mt-1 break-words text-lg font-semibold text-indigo-950">{{ formatMoney(item.forecast) }}</p>
                </div>
            </div>
            <p class="mt-3 text-xs leading-5 text-slate-600">{{ item.explanation }}</p>
        </article>
        <p class="text-xs leading-5 text-slate-500 lg:col-span-2">Referência: {{ planning.month }} · planejamento mensal V1, não os filtros de movimentações. Previsão não é saldo disponível nem check-in diário confirmado.</p>
    </section>
</template>
