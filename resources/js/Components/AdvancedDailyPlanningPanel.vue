<script setup>
import HelpHint from '@/Components/HelpHint.vue';
import { CalendarCheck2, CircleDollarSign, Gauge, TrendingDown, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    dailyPlanning: { type: Object, required: true },
    planning: { type: Object, required: true },
});

const emit = defineEmits(['open-check-ins']);

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => value === null || value === undefined ? '—' : money.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', timeZone: 'UTC' })
    .format(new Date(`${value}T12:00:00Z`));

const dailyMax = computed(() => Math.max(
    1,
    ...props.dailyPlanning.daily.flatMap((day) => [Number(day.budget), Number(day.spent)]),
));
const dailyWidth = (value) => `${Math.min(100, Math.max(0, Number(value) / dailyMax.value * 100))}%`;

const cumulativeMax = computed(() => Math.max(
    1,
    ...props.dailyPlanning.daily.map((day) => Math.abs(Number(day.cumulative_margin))),
));
const cumulativeWidth = (value) => `${Math.min(100, Math.abs(Number(value)) / cumulativeMax.value * 100)}%`;

const statusText = computed(() => {
    if (props.dailyPlanning.tracked_completed_days === 0) {
        return 'Ainda não há dia encerrado com orçamento aplicável para conferir.';
    }

    return props.dailyPlanning.pending_days === 0
        ? 'Todos os dias rastreados deste mês estão confirmados.'
        : `${props.dailyPlanning.pending_days} dia(s) encerrado(s) ainda estão pendentes de conferência.`;
});
</script>

<template>
    <div class="mt-5 space-y-5">
        <section
            v-if="dailyPlanning.confirmed_days === 0"
            class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-950 shadow-sm sm:p-5"
            aria-live="polite"
        >
            <h2 class="font-semibold">Ainda não há dias confirmados para analisar</h2>
            <p class="mt-1 text-sm leading-6 text-amber-900">
                A Análise avançada usa os dias que você confirma no check-in. Enquanto nenhum dia estiver confirmado,
                os totais e gráficos históricos ficam sem dados para não inventar um resultado.
            </p>
            <p v-if="dailyPlanning.pending_days > 0" class="mt-2 text-sm font-medium">
                Você tem {{ dailyPlanning.pending_days }} dia(s) aguardando conferência.
            </p>
            <p v-else-if="dailyPlanning.tracked_completed_days === 0" class="mt-2 text-sm font-medium">
                Seu orçamento diário está configurado, mas ainda não existe um dia anterior coberto por ele para gerar um check-in.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    v-if="dailyPlanning.pending_days > 0"
                    type="button"
                    class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    @click="emit('open-check-ins')"
                >
                    Conferir dias pendentes
                </button>
                <Link
                    :href="route('daily-budgets.edit')"
                    class="rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100"
                >
                    Configurar orçamento diário
                </Link>
            </div>
        </section>
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Indicadores do planejamento diário">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-700"><TrendingUp :size="16" /></span>
                <p class="mt-3 text-xs text-slate-500">Gasto cotidiano por dia</p>
                <p class="mt-1 text-xl font-semibold text-slate-950">{{ formatMoney(planning.indicators.realized_daily_pace) }}</p>
                <p class="mt-1 text-[11px] leading-4 text-slate-400">Média apenas dos gastos cotidianos dos dias completos deste mês; fixos e extraordinários ficam fora.</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700"><Gauge :size="16" /></span>
                <p class="mt-3 text-xs text-slate-500">Quanto posso gastar por dia</p>
                <p class="mt-1 text-xl font-semibold text-indigo-900">{{ formatMoney(planning.indicators.sustainable_daily_pace) }}</p>
                <p class="mt-1 text-[11px] leading-4 text-slate-400">Considera seu planejamento, o caixa atual e as contas conhecidas.</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><CalendarCheck2 :size="16" /></span>
                    <HelpHint label="Ajuda">É o valor voluntário que você definiu para orientar o gasto do dia. Ele não muda automaticamente.</HelpHint>
                </div>
                <p class="mt-3 text-xs text-slate-500">Meu orçamento diário</p>
                <p class="mt-1 text-xl font-semibold text-slate-950">{{ formatMoney(dailyPlanning.current_daily_budget) }}</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700"><CircleDollarSign :size="16" /></span>
                <p class="mt-3 text-xs text-slate-500">Gasto cotidiano confirmado</p>
                <p class="mt-1 text-xl font-semibold text-slate-950">{{ formatMoney(dailyPlanning.total_spent) }}</p>
                <p class="mt-1 text-[11px] leading-4 text-slate-400">Soma apenas os gastos cotidianos dos dias confirmados. Fixos e extraordinários ficam fora.</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"><TrendingUp :size="16" /></span>
                <p class="mt-3 text-xs text-slate-500">Folga líquida acumulada</p>
                <p class="mt-1 text-xl font-semibold" :class="dailyPlanning.net_margin === null ? 'text-slate-500' : Number(dailyPlanning.net_margin) >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatMoney(dailyPlanning.net_margin) }}</p>
                <p class="mt-1 text-[11px] leading-4 text-slate-400">{{ dailyPlanning.confirmed_days ? `${dailyPlanning.confirmed_days} dia(s) confirmado(s).` : 'Sem base confirmada; pendência não é zero.' }}</p>
            </article>
        </section>

        <section class="grid gap-3 sm:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs text-slate-500">Folga positiva acumulada</p>
                <p class="mt-1 font-semibold text-emerald-700">{{ formatMoney(dailyPlanning.gross_savings) }}</p>
                <p class="mt-1 text-[11px] text-slate-400">Soma apenas os dias em que ficou abaixo do orçamento.</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs text-slate-500">Excesso acumulado</p>
                <p class="mt-1 font-semibold text-rose-700">{{ formatMoney(dailyPlanning.gross_excess) }}</p>
                <p class="mt-1 text-[11px] text-slate-400">Soma apenas os dias em que passou do orçamento.</p>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-950">Orçamento × realizado</h2>
                    <p class="mt-1 text-sm text-slate-500">Cada linha compara o orçamento que valia naquele dia com o gasto elegível confirmado.</p>
                </div>
                <p class="text-xs font-medium" :class="dailyPlanning.pending_days ? 'text-amber-700' : 'text-emerald-700'">{{ statusText }}</p>
            </div>

            <div v-if="dailyPlanning.daily.length" class="mt-5 space-y-4">
                <div v-for="day in dailyPlanning.daily" :key="day.date" class="grid gap-2 sm:grid-cols-[5rem_1fr_8rem] sm:items-center">
                    <p class="text-xs font-semibold text-slate-600">{{ formatDate(day.date) }}</p>
                    <div class="space-y-1.5">
                        <div class="h-2.5 overflow-hidden rounded-full bg-indigo-50"><div class="h-full rounded-full bg-indigo-500" :style="{ width: dailyWidth(day.budget) }" /></div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-slate-700" :style="{ width: dailyWidth(day.spent) }" /></div>
                    </div>
                    <div class="text-right text-[11px] leading-4 text-slate-500">
                        <p>Plano {{ formatMoney(day.budget) }}</p>
                        <p>Gasto {{ formatMoney(day.spent) }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-indigo-500" /> Orçamento aplicável</span>
                    <span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-slate-700" /> Gasto realizado</span>
                </div>
            </div>
            <p v-else class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Ainda não há dias confirmados para formar o gráfico.</p>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <h2 class="font-semibold text-slate-950">Folga líquida acumulada</h2>
                <p class="mt-1 text-sm text-slate-500">Mostra a evolução do resultado frente ao orçamento, sem transformar folga em saldo ou receita.</p>
                <div v-if="dailyPlanning.daily.length" class="mt-5 space-y-3">
                    <div v-for="day in dailyPlanning.daily" :key="`margin-${day.date}`" class="grid grid-cols-[4.5rem_1fr_7rem] items-center gap-2">
                        <span class="text-xs text-slate-500">{{ formatDate(day.date) }}</span>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full"
                                :class="Number(day.cumulative_margin) >= 0 ? 'bg-emerald-500' : 'bg-rose-500'"
                                :style="{ width: cumulativeWidth(day.cumulative_margin) }"
                            />
                        </div>
                        <span class="text-right text-xs font-semibold" :class="Number(day.cumulative_margin) >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatMoney(day.cumulative_margin) }}</span>
                    </div>
                </div>
                <p v-else class="mt-5 text-sm text-slate-500">A série começa após o primeiro check-in confirmado.</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-950">Planejamento do restante do mês</h2>
                        <p class="mt-1 text-sm text-slate-500">Esta parte mostra uma estimativa do planejamento mensal. Ela não é saldo disponível e não substitui os dias confirmados acima.</p>
                    </div>
                    <HelpHint label="Ajuda">Os valores projetados deste bloco ainda vêm do planejamento mensal vigente da V1. Eles ficam separados dos check-ins confirmados da V2 para evitar dupla contagem.</HelpHint>
                </div>

                <div v-if="planning.configured" class="mt-4 space-y-3">
                    <div class="rounded-xl bg-emerald-50 p-3">
                        <p class="text-xs font-medium text-emerald-800">Receitas</p>
                        <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-600">Recebido</span><strong class="text-emerald-950">{{ formatMoney(planning.income.received) }}</strong>
                        </div>
                        <div class="mt-1 flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-600">Ainda previsto</span><strong class="text-indigo-900">{{ formatMoney(planning.income.pending) }}</strong>
                        </div>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs font-medium text-slate-700">Despesas variáveis do planejamento mensal</p>
                        <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-600">Já considerado no planejamento</span><strong class="text-slate-950">{{ formatMoney(planning.variable.realized) }}</strong>
                        </div>
                        <div class="mt-1 flex items-center justify-between gap-3 text-sm">
                            <span class="text-slate-600">Estimativa de fechamento</span><strong class="text-indigo-900">{{ formatMoney(planning.variable.projected) }}</strong>
                        </div>
                        <p class="mt-2 text-[11px] leading-4 text-slate-500">Não some este bloco ao gasto cotidiano confirmado acima: uma visão acompanha os check-ins e a outra estima o planejamento do mês.</p>
                    </div>
                    <div class="rounded-xl border border-dashed border-slate-200 p-3 text-xs leading-5 text-slate-600">
                        <strong class="text-slate-800">Capacidade diária estimada:</strong>
                        {{ formatMoney(planning.indicators.sustainable_daily_pace) }} · {{ planning.daily.remaining_days }} dia(s) restantes.
                        É o menor valor entre o que o planejamento permite e o que seu caixa atual comporta depois das contas conhecidas. Não altera seu orçamento diário.
                    </div>
                </div>
                <p v-else class="mt-4 rounded-xl border border-dashed border-amber-200 bg-amber-50 p-3 text-sm leading-5 text-amber-900">Configure proteção e essenciais do mês para liberar os cenários projetados. Os indicadores confirmados da V2 continuam válidos separadamente.</p>
            </article>
        </section>
    </div>
</template>
