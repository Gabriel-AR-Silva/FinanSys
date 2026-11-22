<script setup>
import { computed, ref } from 'vue';
import { money } from './format';

const props = defineProps({
    companies: { type: Array, required: true },
    production: { type: Array, required: true },
    today: { type: String, required: true },
});
const mode = ref('finance');
const chartType = ref('line');
const period = ref(6);
const selected = ref({ finance: ['sales', 'received', 'cost'], volume: ['sold', 'manufactured'] });
const activeMonth = ref(null);
const definitions = {
    finance: [
        { key: 'sales', label: 'Valor vendido', color: 'var(--fs-info)', dash: '', description: 'Total das vendas pela data da venda.' },
        { key: 'received', label: 'Recebido', color: 'var(--fs-success)', dash: '8 3', description: 'Pagamentos pela data real do recebimento.' },
        { key: 'cost', label: 'Custo informado', color: 'var(--fs-danger)', dash: '2 3', description: 'Custos conhecidos dos lotes pela data de fabricação; não representa desembolso no caixa.' },
        { key: 'pending', label: 'Saldo a receber por mês de venda', color: 'var(--fs-warning)', dash: '12 4 2 4', description: 'Saldo atual das vendas agrupado pelo mês da venda, não é previsão de recebimento.' },
    ],
    volume: [
        { key: 'sold', label: 'Vendidas', color: 'var(--fs-info)', dash: '', description: 'Quantidade vendida pela data da venda.' },
        { key: 'manufactured', label: 'Fabricadas', color: 'var(--fs-success)', dash: '8 3', description: 'Quantidade dos lotes concluídos pela data de fabricação.' },
        { key: 'defects', label: 'Defeituosas por mês de venda', color: 'var(--fs-danger)', dash: '2 3', description: 'Defeitos atuais agrupados pelo mês da venda, não pela data do defeito.' },
    ],
};
const visibleSeries = computed(() => definitions[mode.value].filter(item => selected.value[mode.value].includes(item.key)));
function toggle(key) {
    const current = selected.value[mode.value];
    if (current.includes(key) && current.length === 1) return;
    selected.value[mode.value] = current.includes(key) ? current.filter(item => item !== key) : [...current, key];
}
const dateLabel = new Intl.DateTimeFormat('pt-BR', { month: 'short', year: '2-digit', timeZone: 'UTC' });
const report = computed(() => {
    const [year, month] = props.today.split('-').map(Number);
    const rows = Array.from({ length: period.value }, (_, index) => {
        const date = new Date(Date.UTC(year, month - period.value + index, 1));
        return { key: date.toISOString().slice(0, 7), label: dateLabel.format(date), sales: 0, received: 0, cost: 0, pending: 0, sold: 0, manufactured: 0, defects: 0 };
    });
    const byMonth = new Map(rows.map(row => [row.key, row]));
    const undated = { received: 0, cost: 0, manufactured: 0, unknownCosts: 0 };
    let hasData = false;
    for (const company of props.companies) {
        for (const sale of company.sales ?? []) {
            const row = byMonth.get(sale.sold_on?.slice(0, 7));
            if (row) {
                hasData = true;
                row.sales += sale.total_cents;
                row.pending += sale.balance_cents;
                row.sold += sale.plates.length;
                row.defects += sale.plates.filter(plate => plate.defective).length;
            }
            for (const payment of sale.payments ?? []) {
                if (!payment.received_on) undated.received += payment.amount_cents;
                const paymentRow = byMonth.get(payment.received_on?.slice(0, 7));
                if (paymentRow) { paymentRow.received += payment.amount_cents; hasData = true; }
            }
        }
    }
    for (const batch of props.production) {
        const row = byMonth.get(batch.produced_on?.slice(0, 7));
        if (batch.cost_cents == null) undated.unknownCosts++;
        else if (!batch.produced_on) undated.cost += batch.cost_cents;
        if (!batch.produced_on && batch.status === 'completed') undated.manufactured += batch.quantity;
        if (row) {
            hasData = true;
            row.cost += batch.cost_cents ?? 0;
            if (batch.status === 'completed') row.manufactured += batch.quantity;
        }
    }
    return { rows, undated, hasData };
});
const bounds = { left: 104, right: 880, top: 18, bottom: 216 };
const maxValue = computed(() => {
    const highest = Math.max(0, ...report.value.rows.flatMap(row => visibleSeries.value.map(series => row[series.key])));
    const unit = mode.value === 'finance' ? 100 : 1;
    return Math.max(unit * 4, Math.ceil(highest / (unit * 4)) * unit * 4);
});
const ticks = computed(() => Array.from({ length: 5 }, (_, index) => maxValue.value * index / 4));
const slot = computed(() => (bounds.right - bounds.left) / report.value.rows.length);
const x = index => bounds.left + slot.value * (index + 0.5);
const y = value => bounds.bottom - (value / maxValue.value) * (bounds.bottom - bounds.top);
const barWidth = computed(() => Math.min(36, slot.value * 0.75 / visibleSeries.value.length));
const format = value => mode.value === 'finance' ? money(value) : new Intl.NumberFormat('pt-BR').format(value);
const activeRow = computed(() => report.value.rows.find(row => row.key === activeMonth.value) ?? report.value.rows.at(-1));
function points(key) {
    return report.value.rows.map((row, index) => x(index) + ',' + y(row[key])).join(' ');
}
</script>

<template>
    <section v-if="today" class="nfc-panel flex min-w-0 flex-col gap-4 p-4" aria-labelledby="nfc-chart-heading">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="nfc-chart-heading" class="font-semibold">Evolução do negócio</h2>
                <p class="nfc-muted text-xs">Por mês · o mês atual está em andamento.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <label class="nfc-field text-xs">Indicadores<select v-model="mode" class="nfc-control"><option value="finance">Financeiro</option><option value="volume">Plaquinhas</option></select></label>
                <label class="nfc-field text-xs">Visualização<select v-model="chartType" class="nfc-control"><option value="line">Linhas</option><option value="bar">Barras</option></select></label>
                <label class="nfc-field text-xs">Período<select v-model.number="period" class="nfc-control"><option :value="3">3 meses</option><option :value="6">6 meses</option><option :value="12">12 meses</option></select></label>
            </div>
        </header>
        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Séries visíveis">
            <button v-for="series in definitions[mode]" :key="series.key" type="button" class="nfc-button text-xs" :aria-pressed="selected[mode].includes(series.key)" :disabled="selected[mode].includes(series.key) && selected[mode].length === 1" @click="toggle(series.key)">
                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: series.color, opacity: selected[mode].includes(series.key) ? 1 : 0.3 }" aria-hidden="true"></span>{{ series.label }}
            </button>
        </div>
        <p v-if="!report.hasData" class="nfc-muted text-sm">Nenhum registro com data no período selecionado.</p>
        <div v-else class="nfc-scrollbar min-w-0 overflow-x-auto">
            <svg viewBox="0 0 900 256" class="block w-full min-w-[560px]" role="img" :aria-label="'Gráfico ' + (chartType === 'line' ? 'de linhas' : 'de barras') + ' mensais em ' + (mode === 'finance' ? 'reais' : 'unidades')">
                <g v-for="tick in ticks" :key="tick">
                    <line :x1="bounds.left" :x2="bounds.right" :y1="y(tick)" :y2="y(tick)" stroke="var(--fs-border-soft)" />
                    <text :x="bounds.left - 8" :y="y(tick) + 4" font-size="12" text-anchor="end" fill="var(--fs-text-secondary)">{{ format(tick) }}</text>
                </g>
                <g v-if="chartType === 'line'">
                    <g v-for="series in visibleSeries" :key="series.key">
                        <polyline :points="points(series.key)" fill="none" :stroke="series.color" stroke-width="2.5" :stroke-dasharray="series.dash" stroke-linejoin="round" />
                        <circle v-for="(row, index) in report.rows" :key="row.key" :cx="x(index)" :cy="y(row[series.key])" r="3.5" :fill="series.color"><title>{{ row.label }} · {{ series.label }}: {{ format(row[series.key]) }}</title></circle>
                    </g>
                </g>
                <g v-else>
                    <g v-for="(row, index) in report.rows" :key="row.key">
                        <rect v-for="(series, seriesIndex) in visibleSeries" :key="series.key" :x="x(index) + (seriesIndex - visibleSeries.length / 2) * barWidth" :y="y(row[series.key])" :width="Math.max(1, barWidth - 2)" :height="bounds.bottom - y(row[series.key])" :fill="series.color"><title>{{ row.label }} · {{ series.label }}: {{ format(row[series.key]) }}</title></rect>
                    </g>
                </g>
                <g v-for="(row, index) in report.rows" :key="row.key">
                    <text :x="x(index)" y="240" text-anchor="middle" font-size="12" fill="var(--fs-text-secondary)">{{ row.label }}</text>
                    <rect :x="x(index) - slot / 2" :y="bounds.top" :width="slot" :height="bounds.bottom - bounds.top" fill="transparent" @pointerenter="activeMonth = row.key" @click="activeMonth = row.key" />
                </g>
            </svg>
        </div>
        <div v-if="report.hasData && activeRow" class="nfc-rule border-t pt-3">
            <label class="nfc-field max-w-48 text-xs">Detalhe mensal<select :value="activeRow.key" class="nfc-control" @change="activeMonth = $event.target.value"><option v-for="row in report.rows" :key="row.key" :value="row.key">{{ row.label }}</option></select></label>
            <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="series in visibleSeries" :key="series.key" class="min-w-0"><dt class="nfc-muted text-xs">{{ series.label }}</dt><dd class="break-words font-semibold tabular-nums">{{ format(activeRow[series.key]) }}</dd></div>
            </dl>
        </div>
        <details class="nfc-rule border-t pt-3">
            <summary class="cursor-pointer text-sm font-medium">Como os valores são calculados e dados em tabela</summary>
            <ul class="nfc-muted mt-2 list-disc space-y-1 pl-5 text-xs">
                <li v-for="series in definitions[mode]" :key="series.key">{{ series.label }}: {{ series.description }}</li>
                <li v-if="mode === 'finance'">Fora da série temporal (todos os períodos): {{ money(report.undated.received) }} recebidos sem data, {{ money(report.undated.cost) }} de custos sem data e {{ report.undated.unknownCosts }} lote(s) com custo não informado. Não é lucro contábil.</li>
                <li v-else>Fora da série temporal: {{ report.undated.manufactured }} unidades fabricadas sem data.</li>
            </ul>
            <div class="nfc-scrollbar mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead><tr><th scope="col" class="p-2">Mês</th><th v-for="series in visibleSeries" :key="series.key" scope="col" class="p-2">{{ series.label }}</th></tr></thead>
                    <tbody><tr v-for="row in report.rows" :key="row.key" class="nfc-rule border-t"><th scope="row" class="p-2 font-medium">{{ row.label }}</th><td v-for="series in visibleSeries" :key="series.key" class="p-2 tabular-nums">{{ format(row[series.key]) }}</td></tr></tbody>
                </table>
            </div>
        </details>
    </section>
</template>
