<script setup>
import { computed } from 'vue';

const props = defineProps({
    cashFlow: { type: Object, required: true },
});
const width = 720;
const height = 176;
const baseline = 142;
const top = 16;
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(`${value}T12:00:00`));
const maxValue = computed(() => Math.max(...props.cashFlow.points.flatMap((point) => [Number(point.income), Number(point.expense)]), 1));
const slotWidth = computed(() => width / Math.max(props.cashFlow.points.length, 1));
const barWidth = computed(() => Math.max(2, Math.min(10, slotWidth.value * 0.34)));
const barHeight = (value) => Number(value) / maxValue.value * (baseline - top);
const labels = computed(() => {
    const points = props.cashFlow.points;
    if (!points.length) return [];
    return [0, Math.floor((points.length - 1) / 2), points.length - 1].map((index) => ({ index, date: points[index].date }));
});
</script>

<template>
    <article class="min-w-0 overflow-hidden rounded-2xl border p-4 shadow-sm sm:p-5" style="border-color: var(--fs-border-soft); background: linear-gradient(135deg, var(--fs-surface), var(--fs-surface-subtle)); color: var(--fs-text);">
        <div>
            <h2 class="text-sm font-semibold" style="color: var(--fs-text);">Dinheiro que entrou e saiu</h2>
            <p class="mt-0.5 text-xs" style="color: var(--fs-text-muted);">Mostra quando o dinheiro realmente entrou ou saiu da conta. Pagar a fatura do cartão reduz o saldo, mas não conta a compra de novo.</p>
        </div>
        <div class="mt-4 w-full max-w-full overflow-x-auto rounded-xl px-2 pt-2" style="background: var(--fs-surface-elevated);">
            <svg class="h-44 w-full min-w-[34rem]" :viewBox="`0 0 ${width} ${height}`" role="img" aria-label="Gráfico diário do dinheiro que entrou e saiu">
                <line v-for="y in [34, 70, 106, 142]" :key="y" x1="0" :y1="y" :x2="width" :y2="y" stroke="var(--fs-border-soft)" stroke-width="1" stroke-dasharray="4 5" />
                <line x1="0" :y1="baseline" :x2="width" :y2="baseline" stroke="var(--fs-border)" stroke-width="1" />
                <g v-for="(point, index) in cashFlow.points" :key="point.date">
                    <rect :x="index * slotWidth + slotWidth / 2 - barWidth - 1" :y="baseline - barHeight(point.income)" :width="barWidth" :height="barHeight(point.income)" fill="var(--fs-success)" rx="3"><title>{{ formatDate(point.date) }} — entradas de caixa {{ formatMoney(point.income) }}</title></rect>
                    <rect :x="index * slotWidth + slotWidth / 2 + 1" :y="baseline - barHeight(point.expense)" :width="barWidth" :height="barHeight(point.expense)" fill="var(--fs-danger)" rx="3"><title>{{ formatDate(point.date) }} — saídas de caixa {{ formatMoney(point.expense) }}</title></rect>
                </g>
                <text v-for="label in labels" :key="label.index" :x="label.index * slotWidth + slotWidth / 2" y="169" text-anchor="middle" fill="var(--fs-text-muted)" font-size="11">{{ formatDate(label.date) }}</text>
            </svg>
        </div>
        <div class="mt-2 flex gap-4 text-xs" style="color: var(--fs-text-muted);">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: var(--fs-success);" />Entradas</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: var(--fs-danger);" />Saídas</span>
        </div>
    </article>
</template>
