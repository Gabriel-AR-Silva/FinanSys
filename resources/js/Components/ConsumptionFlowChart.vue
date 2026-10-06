<script setup>
import { computed } from 'vue';

const props = defineProps({
    consumptionFlow: { type: Object, required: true },
});
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
const areaPoints = computed(() => props.consumptionFlow.points.length
    ? `${pointX(0)},${baseline} ${linePoints.value} ${pointX(props.consumptionFlow.points.length - 1)},${baseline}`
    : '');
const labels = computed(() => {
    const points = props.consumptionFlow.points;
    if (!points.length) return [];
    return [0, Math.floor((points.length - 1) / 2), points.length - 1].map(index => ({ index, date: points[index].date }));
});
</script>

<template>
    <article class="min-w-0 overflow-hidden rounded-2xl border p-4 shadow-sm sm:p-5" style="border-color: var(--fs-border-soft); background: linear-gradient(135deg, var(--fs-surface), var(--fs-surface-subtle)); color: var(--fs-text);">
        <h2 class="text-sm font-semibold" style="color: var(--fs-text);">Quanto gastei por dia</h2>
        <p class="mt-0.5 text-xs" style="color: var(--fs-text-muted);">Conta cada despesa no dia em que ela aconteceu, inclusive compras no cartão. Pagar a fatura depois não conta a mesma despesa de novo.</p>
        <div class="mt-4 w-full max-w-full overflow-x-auto rounded-xl px-2 pt-2" style="background: var(--fs-surface-elevated);">
            <svg class="h-44 w-full min-w-[34rem]" :viewBox="`0 0 ${width} ${height}`" role="img" aria-label="Gráfico diário de quanto foi gasto">
                <defs>
                    <linearGradient id="consumption-area" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--fs-danger)" stop-opacity="0.28" />
                        <stop offset="68%" stop-color="var(--fs-warning)" stop-opacity="0.10" />
                        <stop offset="100%" stop-color="var(--fs-surface-elevated)" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <line v-for="y in [34, 70, 106, 142]" :key="y" x1="0" :y1="y" :x2="width" :y2="y" stroke="var(--fs-border-soft)" stroke-width="1" stroke-dasharray="4 5" />
                <line x1="0" :y1="baseline" :x2="width" :y2="baseline" stroke="var(--fs-border)" stroke-width="1" />
                <polygon v-if="consumptionFlow.points.length" :points="areaPoints" fill="url(#consumption-area)" />
                <polyline v-if="consumptionFlow.points.length" :points="linePoints" fill="none" stroke="var(--fs-danger)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                <g v-for="(point, index) in consumptionFlow.points" :key="point.date">
                    <circle :cx="pointX(index)" :cy="pointY(point.realized)" r="4" fill="var(--fs-warning)" stroke="var(--fs-surface-elevated)" stroke-width="1.5"><title>{{ formatDate(point.date) }} — gasto {{ formatMoney(point.realized) }}</title></circle>
                </g>
                <text v-for="label in labels" :key="label.index" :x="label.index * slotWidth + slotWidth / 2" y="169" text-anchor="middle" fill="var(--fs-text-muted)" font-size="11">{{ formatDate(label.date) }}</text>
            </svg>
        </div>
    </article>
</template>
