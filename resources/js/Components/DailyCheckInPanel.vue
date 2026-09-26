<script setup>
import Modal from '@/Components/Modal.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CalendarCheck2, CheckCircle2, X } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    days: { type: Array, required: true },
});

const selected = ref([]);
const pendingDays = computed(() => props.days.filter((day) => day.status === 'pending'));
const confirmedDays = computed(() => props.days.filter((day) => day.status === 'confirmed'));
const confirmedCount = computed(() => confirmedDays.value.length);
const showModal = ref(pendingDays.value.length > 0);
const showCorrectionModal = ref(false);
const correctingDay = ref(null);
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => value === null || value === undefined ? '—' : currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));

const form = useForm({ days: [] });
const correctionForm = useForm({
    date: '',
    reason: '',
    operation_id: '',
});

const toggle = (date) => {
    selected.value = selected.value.includes(date)
        ? selected.value.filter((item) => item !== date)
        : [...selected.value, date];
};

const confirmSelected = () => {
    form.days = selected.value.map((date) => ({
        date,
        operation_id: crypto.randomUUID(),
        reason: null,
    }));
    form.post(route('daily-check-ins.batch.store'), {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
            showModal.value = false;
        },
    });
};

const openCorrection = (day) => {
    correctingDay.value = day;
    correctionForm.date = day.date;
    correctionForm.reason = '';
    correctionForm.operation_id = crypto.randomUUID();
    correctionForm.clearErrors();
    showCorrectionModal.value = true;
};

const closeCorrection = () => {
    if (correctionForm.processing) return;
    showCorrectionModal.value = false;
    correctingDay.value = null;
    correctionForm.reset();
};

const submitCorrection = () => {
    correctionForm.patch(route('daily-check-ins.correct'), {
        preserveScroll: true,
        onSuccess: closeCorrection,
    });
};
</script>

<template>
    <section
        v-if="pendingDays.length"
        class="mt-4 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        aria-labelledby="pending-check-ins-heading"
    >
        <div class="flex gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                <CalendarCheck2 :size="18" />
            </span>
            <div>
                <h2 id="pending-check-ins-heading" class="font-semibold text-slate-950">
                    {{ pendingDays.length }} dia(s) aguardando conferência
                </h2>
                <p class="mt-1 text-sm leading-5 text-slate-600">
                    Dias não conferidos continuam pendentes; ausência de lançamento não vira zero automaticamente.
                </p>
            </div>
        </div>
        <button
            type="button"
            class="shrink-0 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white"
            @click="showModal = true"
        >
            Conferir dias
        </button>
    </section>

    <Modal :show="showModal && pendingDays.length > 0" max-width="2xl" @close="showModal = false">
        <div class="p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                        <CalendarCheck2 :size="19" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Conferir dias pendentes</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-600">
                            Confira os valores registrados. Se esqueceu algo, adicione a despesa antes de confirmar.
                        </p>
                    </div>
                </div>
                <button type="button" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Fechar" @click="showModal = false">
                    <X :size="18" />
                </button>
            </div>

            <div class="mt-5 max-h-80 space-y-2 overflow-y-auto pr-1">
                <label
                    v-for="day in pendingDays"
                    :key="day.date"
                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3"
                >
                    <input
                        type="checkbox"
                        class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        :checked="selected.includes(day.date)"
                        @change="toggle(day.date)"
                    >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-900">{{ formatDate(day.date) }}</span>
                            <span class="text-sm font-semibold text-slate-700">Registrado: {{ formatMoney(day.preview_spent) }}</span>
                        </div>
                        <p v-if="day.blockers.length" class="mt-1 flex items-start gap-1 text-xs leading-5 text-rose-700">
                            <AlertTriangle :size="14" class="mt-0.5 shrink-0" />
                            Existem dados incompletos. Corrija os lançamentos antes de confirmar.
                        </p>
                        <p v-else class="mt-1 flex items-center gap-1 text-xs text-slate-500">
                            <CheckCircle2 :size="14" />
                            Valor reconciliado pronto para conferência.
                        </p>
                    </div>
                </label>
            </div>

            <p v-if="form.errors.days" class="mt-3 text-sm text-rose-700">{{ form.errors.days }}</p>
            <p v-if="form.errors['days.0.date']" class="mt-3 text-sm text-rose-700">{{ form.errors['days.0.date'] }}</p>
            <p v-if="form.errors.eligible_spent" class="mt-3 text-sm text-rose-700">{{ form.errors.eligible_spent }}</p>
            <p v-if="form.errors.daily_budget" class="mt-3 text-sm text-rose-700">
                {{ form.errors.daily_budget }}
                <Link :href="route('daily-budgets.edit')" class="font-semibold underline">Configurar orçamento diário</Link>
            </p>

            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-3 text-xs">
                    <Link :href="route('ledger-entries.index')" class="font-semibold text-slate-600 hover:text-slate-950">
                        Revisar/adicionar despesas
                    </Link>
                    <Link :href="route('daily-budgets.edit')" class="font-semibold text-emerald-700 hover:text-emerald-800">
                        Orçamento diário
                    </Link>
                </div>
                <button
                    type="button"
                    :disabled="form.processing || selected.length === 0"
                    class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
                    @click="confirmSelected"
                >
                    {{ form.processing ? 'Confirmando…' : `Confirmar ${selected.length || ''} dia(s)` }}
                </button>
            </div>

            <div v-if="confirmedCount" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <p class="text-xs text-slate-500">
                    {{ confirmedCount }} dia(s) deste mês já confirmado(s).
                </p>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    @click="openCorrection(confirmedDays[confirmedDays.length - 1])"
                >
                    Corrigir último dia confirmado
                </button>
            </div>
        </div>
    </Modal>

    <section v-if="confirmedCount && !pendingDays.length" class="mt-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-slate-900">{{ confirmedCount }} dia(s) confirmado(s) neste mês</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">Se você adicionar ou corrigir um lançamento depois da conferência, registre uma nova revisão do dia.</p>
        </div>
        <button
            type="button"
            class="shrink-0 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            @click="openCorrection(confirmedDays[confirmedDays.length - 1])"
        >
            Corrigir último dia
        </button>
    </section>

    <Modal :show="showCorrectionModal && Boolean(correctingDay)" max-width="lg" @close="closeCorrection">
        <form class="p-5 sm:p-6" @submit.prevent="submitCorrection">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Corrigir conferência diária</h2>
                    <p class="mt-1 text-sm leading-5 text-slate-600">
                        O FinanSys vai recalcular o dia {{ correctingDay ? formatDate(correctingDay.date) : '' }} com os lançamentos atuais e preservar a revisão anterior.
                    </p>
                </div>
                <button type="button" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="Fechar correção" @click="closeCorrection">
                    <X :size="18" />
                </button>
            </div>

            <div v-if="correctingDay" class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <div class="flex flex-wrap justify-between gap-2">
                    <span>Valor confirmado anteriormente</span>
                    <strong>{{ formatMoney(correctingDay.spent) }}</strong>
                </div>
                <p class="mt-1 text-xs text-slate-500">O novo valor não é digitado manualmente: ele vem dos lançamentos financeiros atuais.</p>
            </div>

            <label for="daily-check-in-correction-reason" class="mt-5 block text-sm font-semibold text-slate-800">
                Motivo da correção
            </label>
            <textarea
                id="daily-check-in-correction-reason"
                v-model="correctionForm.reason"
                rows="3"
                maxlength="255"
                required
                class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                placeholder="Ex.: adicionei uma despesa que havia esquecido"
            />
            <p v-if="correctionForm.errors.reason" class="mt-2 text-sm text-rose-700">{{ correctionForm.errors.reason }}</p>
            <p v-if="correctionForm.errors.date" class="mt-2 text-sm text-rose-700">{{ correctionForm.errors.date }}</p>

            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="closeCorrection">
                    Cancelar
                </button>
                <button
                    type="submit"
                    :disabled="correctionForm.processing || !correctionForm.reason.trim()"
                    class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ correctionForm.processing ? 'Corrigindo…' : 'Registrar correção' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
