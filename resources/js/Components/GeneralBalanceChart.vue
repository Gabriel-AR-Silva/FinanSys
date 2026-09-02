<script setup>
import { TrendingDown, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({ chart: { type: Object, required: true } });
const width = 900;
const lineTop = 24;
const lineBottom = 140;
const horizontalPadding = 18;
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const percent = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatMoney = (value) => currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(`${value}T12:00:00`));

const coordinates = computed(() => {
    const points = props.chart.points;
    const balances = points.map((point) => Number(point.balance));
    const min = Math.min(...balances);
    const max = Math.max(...balances);
    const span = max - min || 1;
    const step = points.length > 1 ? (width - horizontalPadding * 2) / (points.length - 1) : 0;

    return points.map((point, index) => ({
        ...point,
        x: horizontalPadding + index * step,
        y: lineBottom - ((Number(point.balance) - min) / span) * (lineBottom - lineTop),
    }));
});

const path = computed(() => coordinates.value.map((point, index) => `${index ? 'L' : 'M'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' '));
const labels = computed(() => {
    const points = coordinates.value;
    if (!points.length) return [];
    return [points[0], points[Math.floor((points.length - 1) / 2)], points[points.length - 1]];
});
const positive = computed(() => Number(props.chart.change) >= 0);

</script>

<template>
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
            <div><p class="text-sm font-semibold text-slate-950">Evolução da verba geral</p><p class="mt-1 text-sm text-slate-500">Saldo acumulado e movimentação diária do período.</p></div>
            <div class="flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold" :class="positive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><TrendingUp v-if="positive" :size="15" /><TrendingDown v-else :size="15" /><span v-if="chart.change_percentage !== null">{{ positive ? '+' : '' }}{{ percent.format(Number(chart.change_percentage)) }}%</span><span v-else>Sem comparação</span></div>
        </div>

        <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-400">Variação no período</p><p class="mt-1 text-2xl font-semibold tracking-tight" :class="positive ? 'text-emerald-700' : 'text-rose-700'">{{ positive ? '+' : '' }}{{ formatMoney(chart.change) }}</p></div>
        </div>

        <div class="mt-3 overflow-x-auto">
            <svg class="h-44 min-w-[34rem] w-full" :viewBox="`0 0 ${width} 176`" role="img" aria-label="Gráfico da evolução do saldo geral">
                <defs><linearGradient id="balance-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#0f172a" stop-opacity="0.16" /><stop offset="100%" stop-color="#0f172a" stop-opacity="0" /></linearGradient></defs>
                <path v-if="coordinates.length" :d="`${path} L ${coordinates[coordinates.length - 1].x} ${lineBottom} L ${coordinates[0].x} ${lineBottom} Z`" fill="url(#balance-area)" />
                <path v-if="coordinates.length" :d="path" fill="none" stroke="#0f172a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <circle v-for="point in coordinates" :key="point.date" :cx="point.x" :cy="point.y" r="2" fill="#0f172a"><title>{{ formatDate(point.date) }} — {{ formatMoney(point.balance) }}</title></circle>
                <g v-for="label in labels" :key="label.date"><text :x="label.x" y="169" text-anchor="middle" fill="#64748b" font-size="11">{{ formatDate(label.date) }}</text></g>
            </svg>
        </div>
    </article>
</template>
