<script setup>
import { router } from '@inertiajs/vue3';
import { TrendingDown, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({ chart: { type: Object, required: true } });
const periods = [{ days: 7, label: '7 dias' }, { days: 15, label: '15 dias' }, { days: 30, label: '30 dias' }, { days: 60, label: '60 dias' }, { days: 365, label: '1 ano' }];
const width = 900;
const lineTop = 24;
const lineBottom = 166;
const barBaseline = 232;
const barMaxHeight = 48;
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
const maxAbsoluteChange = computed(() => Math.max(...props.chart.points.map((point) => Math.abs(Number(point.change))), 1));
const barWidth = computed(() => Math.max(1.5, Math.min(10, (width - horizontalPadding * 2) / props.chart.points.length - 1)));
const barHeight = (change) => Math.max(Number(change) === 0 ? 0 : 2, Math.abs(Number(change)) / maxAbsoluteChange.value * barMaxHeight);
const labels = computed(() => {
    const points = coordinates.value;
    if (!points.length) return [];
    return [points[0], points[Math.floor((points.length - 1) / 2)], points[points.length - 1]];
});
const positive = computed(() => Number(props.chart.change) >= 0);

const changePeriod = (days) => {
    if (days === props.chart.period) return;
    router.get(route('dashboard'), { period: days }, { preserveScroll: true, preserveState: true, only: ['overview'] });
};
</script>

<template>
    <article class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
            <div><p class="text-sm font-semibold text-slate-950">Evolução da verba geral</p><p class="mt-1 text-sm text-slate-500">Saldo acumulado e movimentação diária do período.</p></div>
            <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
                <button v-for="periodOption in periods" :key="periodOption.days" type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold transition" :class="periodOption.days === chart.period ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'" @click="changePeriod(periodOption.days)">{{ periodOption.label }}</button>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-400">Variação no período</p><p class="mt-1 text-2xl font-semibold tracking-tight" :class="positive ? 'text-emerald-700' : 'text-rose-700'">{{ positive ? '+' : '' }}{{ formatMoney(chart.change) }}</p></div>
            <div class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold" :class="positive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><TrendingUp v-if="positive" :size="17" /><TrendingDown v-else :size="17" /><span v-if="chart.change_percentage !== null">{{ positive ? '+' : '' }}{{ percent.format(Number(chart.change_percentage)) }}%</span><span v-else>Sem base de comparação</span></div>
        </div>

        <div class="mt-5 overflow-x-auto">
            <svg class="h-auto min-w-[42rem] w-full" :viewBox="`0 0 ${width} 276`" role="img" aria-label="Gráfico da evolução do saldo geral">
                <defs><linearGradient id="balance-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#0f172a" stop-opacity="0.16" /><stop offset="100%" stop-color="#0f172a" stop-opacity="0" /></linearGradient></defs>
                <line x1="18" y1="232" x2="882" y2="232" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="4 5" />
                <path v-if="coordinates.length" :d="`${path} L ${coordinates[coordinates.length - 1].x} ${lineBottom} L ${coordinates[0].x} ${lineBottom} Z`" fill="url(#balance-area)" />
                <path v-if="coordinates.length" :d="path" fill="none" stroke="#0f172a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <g v-for="point in coordinates" :key="point.date">
                    <rect :x="point.x - barWidth / 2" :y="Number(point.change) >= 0 ? barBaseline - barHeight(point.change) : barBaseline" :width="barWidth" :height="barHeight(point.change)" :fill="Number(point.change) >= 0 ? '#10b981' : '#f43f5e'" rx="2"><title>{{ formatDate(point.date) }} — saldo {{ formatMoney(point.balance) }}; variação {{ formatMoney(point.change) }}</title></rect>
                </g>
                <g v-for="label in labels" :key="label.date"><text :x="label.x" y="268" text-anchor="middle" fill="#64748b" font-size="12">{{ formatDate(label.date) }}</text></g>
            </svg>
        </div>
        <div class="mt-2 flex flex-wrap gap-5 text-xs text-slate-500"><span class="flex items-center gap-2"><span class="h-0.5 w-5 bg-slate-900" />Saldo geral</span><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" />Variação positiva</span><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-rose-500" />Variação negativa</span></div>
    </article>
</template>
