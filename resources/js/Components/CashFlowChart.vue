<script setup>
import { computed } from 'vue';

const props = defineProps({ cashFlow: { type: Object, required: true } });
const width = 720;
const height = 176;
const baseline = 142;
const top = 16;
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(`${value}T12:00:00`));
const maxValue = computed(() => Math.max(...props.cashFlow.points.flatMap((point) => [Number(point.income), Number(point.expense)]), 1));
const slotWidth = computed(() => width / Math.max(props.cashFlow.points.length, 1));
const barWidth = computed(() => Math.max(1, Math.min(8, slotWidth.value * 0.32)));
const barHeight = (value) => Number(value) / maxValue.value * (baseline - top);
const labels = computed(() => {
    const points = props.cashFlow.points;
    if (!points.length) return [];
    return [0, Math.floor((points.length - 1) / 2), points.length - 1].map((index) => ({ index, date: points[index].date }));
});
</script>

<template>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div>
            <h2 class="text-sm font-semibold text-slate-950">Entradas e saídas</h2>
            <p class="mt-0.5 text-xs text-slate-500">Comparação diária no filtro selecionado.</p>
        </div>
        <div class="mt-4 w-full max-w-full overflow-x-auto">
            <svg class="h-44 w-full min-w-[34rem]" :viewBox="`0 0 ${width} ${height}`" role="img" aria-label="Gráfico diário de receitas e despesas">
                <line x1="0" :y1="baseline" :x2="width" :y2="baseline" stroke="#cbd5e1" stroke-width="1" />
                <g v-for="(point, index) in cashFlow.points" :key="point.date">
                    <rect :x="index * slotWidth + slotWidth / 2 - barWidth - 1" :y="baseline - barHeight(point.income)" :width="barWidth" :height="barHeight(point.income)" fill="#10b981" rx="2"><title>{{ formatDate(point.date) }} — receitas {{ formatMoney(point.income) }}</title></rect>
                    <rect :x="index * slotWidth + slotWidth / 2 + 1" :y="baseline - barHeight(point.expense)" :width="barWidth" :height="barHeight(point.expense)" fill="#f43f5e" rx="2"><title>{{ formatDate(point.date) }} — despesas {{ formatMoney(point.expense) }}</title></rect>
                </g>
                <text v-for="label in labels" :key="label.index" :x="label.index * slotWidth + slotWidth / 2" y="169" text-anchor="middle" fill="#64748b" font-size="11">{{ formatDate(label.date) }}</text>
            </svg>
        </div>
        <div class="flex gap-4 text-xs text-slate-500"><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" />Receitas</span><span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-rose-500" />Despesas</span></div>
    </article>
</template>
