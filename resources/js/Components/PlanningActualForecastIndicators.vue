<script setup>
import { computed } from 'vue';

const props = defineProps({ planning: { type: Object, required: true } });
const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => money.format(Number(value));
const indicators = computed(() => [
    {
        title: 'Recebimentos',
        confirmedLabel: 'Já recebidos',
        confirmed: props.planning.income.received,
        forecastLabel: 'Ainda previstos',
        forecast: props.planning.income.pending,
        explanation: 'O previsto é apenas o saldo ainda esperado. Recebimentos parciais não são somados duas vezes.',
    },
    {
        title: 'Despesas e compromissos',
        confirmedLabel: 'Considerados realizados',
        confirmed: props.planning.variable.realized,
        forecastLabel: 'Estimativa total do mês',
        forecast: props.planning.variable.projected,
        explanation: 'A estimativa inclui o realizado: não some os dois valores. Estes são variáveis do planejamento V1; despesas fixas e compromissos anteriores são apresentados separadamente no cálculo da margem.',
    },
]);
</script>

<template>
    <section v-if="planning.configured" class="mt-4 grid gap-3 lg:grid-cols-2" aria-label="Realizado e previsto no mês">
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
        <p class="text-xs leading-5 text-slate-500 lg:col-span-2">Referência: {{ planning.month }} · planejamento mensal V1. O previsto não é saldo bancário disponível nem um check-in diário confirmado.</p>
    </section>
</template>
