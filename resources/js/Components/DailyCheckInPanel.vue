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
const confirmedCount = computed(() => props.days.filter((day) => day.status === 'confirmed').length);
const showModal = ref(pendingDays.value.length > 0);
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => value === null || value === undefined ? '—' : currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));

const form = useForm({ days: [] });

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

            <p v-if="confirmedCount" class="mt-3 text-xs text-slate-500">
                {{ confirmedCount }} dia(s) deste mês já confirmado(s).
            </p>
        </div>
    </Modal>
</template>
