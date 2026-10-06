<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { TrendingDown, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({ chart: { type: Object, required: true } });
const page = usePage();
const receivables = computed(() => page.props.receivables ?? null);
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
    <article class="min-w-0 overflow-hidden rounded-2xl border p-4 shadow-sm sm:p-5" style="border-color: var(--fs-border-soft); background: linear-gradient(135deg, var(--fs-surface), var(--fs-surface-subtle)); color: var(--fs-text);">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
            <div>
                <p class="text-sm font-semibold" style="color: var(--fs-text);">Como seu saldo mudou</p>
                <p class="mt-1 text-sm" style="color: var(--fs-text-muted);">Mostra a mudança do saldo conforme o dinheiro entra e sai. Uma compra no cartão só reduz este saldo quando a fatura é paga.</p>
            </div>
            <div class="flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold" :style="positive ? 'background: var(--fs-success-soft-strong); color: var(--fs-success);' : 'background: var(--fs-danger-soft-strong); color: var(--fs-danger);'">
                <TrendingUp v-if="positive" :size="15" />
                <TrendingDown v-else :size="15" />
                <span v-if="chart.change_percentage !== null">{{ positive ? '+' : '' }}{{ percent.format(Number(chart.change_percentage)) }}%</span>
                <span v-else>Sem comparação</span>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-[0.14em]" style="color: var(--fs-text-subtle);">Mudança do saldo no período</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight" :style="positive ? 'color: var(--fs-success);' : 'color: var(--fs-danger);'">{{ positive ? '+' : '' }}{{ formatMoney(chart.change) }}</p>
            </div>
        </div>

        <div class="mt-3 w-full max-w-full overflow-x-auto rounded-xl px-2 pt-2" style="background: var(--fs-surface-elevated);">
            <svg class="h-44 w-full min-w-[34rem]" :viewBox="`0 0 ${width} 176`" role="img" aria-label="Gráfico de como o saldo mudou">
                <defs>
                    <linearGradient id="balance-area" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--fs-success)" stop-opacity="0.24" />
                        <stop offset="65%" stop-color="var(--fs-info)" stop-opacity="0.08" />
                        <stop offset="100%" stop-color="var(--fs-surface-elevated)" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <line v-for="y in [34, 70, 106, 142]" :key="y" x1="0" :y1="y" :x2="width" :y2="y" stroke="var(--fs-border-soft)" stroke-width="1" stroke-dasharray="4 5" />
                <path v-if="coordinates.length" :d="`${path} L ${coordinates[coordinates.length - 1].x} ${lineBottom} L ${coordinates[0].x} ${lineBottom} Z`" fill="url(#balance-area)" />
                <path v-if="coordinates.length" :d="path" fill="none" stroke="var(--fs-success)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                <circle v-for="point in coordinates" :key="point.date" :cx="point.x" :cy="point.y" r="3" fill="var(--fs-info)" stroke="var(--fs-surface-elevated)" stroke-width="1.5"><title>{{ formatDate(point.date) }} — {{ formatMoney(point.balance) }}</title></circle>
                <g v-for="label in labels" :key="label.date"><text :x="label.x" y="169" text-anchor="middle" fill="var(--fs-text-muted)" font-size="11">{{ formatDate(label.date) }}</text></g>
            </svg>
        </div>

        <div v-if="receivables" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3" style="border-color: var(--fs-border-soft); background: var(--fs-info-soft);">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide" style="color: var(--fs-info);">A receber neste mês</p>
                <p class="mt-1 text-xl font-semibold" style="color: var(--fs-text);">{{ formatMoney(receivables.pending) }}</p>
                <p class="mt-1 text-xs" style="color: var(--fs-text-secondary);">{{ receivables.next_due_on ? `Próxima data prevista: ${formatDate(receivables.next_due_on)}` : 'Sem recebimentos pendentes neste mês.' }} Esse dinheiro ainda não entrou e por isso não faz parte do saldo.</p>
            </div>
            <Link :href="route('financial-settings.edit', { month: receivables.month, tab: 'receipts' })" class="rounded-lg px-3 py-2 text-sm font-semibold ring-1" style="background: var(--fs-surface); color: var(--fs-info); --tw-ring-color: var(--fs-border);">Ver recebimentos previstos</Link>
        </div>
    </article>
</template>
