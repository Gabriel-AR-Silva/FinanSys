<script setup>
import { computed } from 'vue';

const props = defineProps({ consumptionFlow: { type: Object, required: true } });
const width = 720;
const height = 176;
const baseline = 142;
const top = 16;
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = value => currency.format(Number(value));
const formatDate = value => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(`${value}T12:00:00`));
const maxValue = computed(() => Math.max(...props.consumptionFlow.points.map(point => Number(point.realized)), 1));
const slotWidth = computed(() => width / Math.max(props.consumptionFlow.points.length, 1));
const pointX = index => index * slotWidth.value + slotWidth.value / 2;
const pointY = value => baseline - Number(value) / maxValue.value * (baseline - top);
const linePoints = computed(() => props.consumptionFlow.points
    .map((point, index) => `${pointX(index)},${pointY(point.realized)}`)
    .join(' '));
const labels = computed(() => {
    const points = props.consumptionFlow.points;
    if (!points.length) return [];
    return [0, Math.floor((points.length - 1) / 2), points.length - 1].map(index => ({ index, date: points[index].date }));
});
</script>

<template>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 class="text-sm font-semibold text-slate-950">Consumo realizado por dia</h2>
        <p class="mt-0.5 text-xs text-slate-500">Reconhece despesas no fato econômico, inclusive compras no cartão na data da compra. Liquidação da fatura não é somada novamente.</p>
        <div class="mt-4 w-full max-w-full overflow-x-auto">
            <svg class="h-44 w-full min-w-[34rem]" :viewBox="`0 0 ${width} ${height}`" role="img" aria-label="Gráfico diário de consumo realizado">
                <line x1="0" :y1="baseline" :x2="width" :y2="baseline" stroke="#cbd5e1" stroke-width="1" />
                <polyline v-if="consumptionFlow.points.length" :points="linePoints" fill="none" stroke="#334155" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                <g v-for="(point, index) in consumptionFlow.points" :key="point.date">
                    <circle :cx="pointX(index)" :cy="pointY(point.realized)" r="3.5" fill="#334155"><title>{{ formatDate(point.date) }} — consumo {{ formatMoney(point.realized) }}</title></circle>
                </g>
                <text v-for="label in labels" :key="label.index" :x="label.index * slotWidth + slotWidth / 2" y="169" text-anchor="middle" fill="#64748b" font-size="11">{{ formatDate(label.date) }}</text>
            </svg>
        </div>
    </article>
</template>
