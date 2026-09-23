<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);
const code = ref('');
const counts = ref({});
const slider = ref(0);
const loading = ref(false);
const form = useForm({ password: '', confirmation_code: '', slider_confirmed: false });

const countLabels = {
    ledger_entries: 'Lançamentos',
    receipt_forecasts: 'Recebimentos previstos',
    card_operations: 'Operações de cartão',
    ofx_imports: 'Importações OFX',
    daily_planning_records: 'Orçamentos diários e check-ins',
    financial_goals: 'Metas financeiras',
    derived_records: 'Análises e avisos',
};

const totalRecords = computed(() => Object.values(counts.value).reduce((total, count) => total + Number(count), 0));
const matches = computed(() => form.confirmation_code.trim().toUpperCase() === code.value);
const canSubmit = computed(() => matches.value && form.password.length > 0 && slider.value === 100 && !form.processing);

watch(() => props.show, async (show) => {
    if (!show) return;
    code.value = '';
    counts.value = {};
    slider.value = 0;
    form.reset();
    form.clearErrors();
    loading.value = true;
    try {
        const response = await axios.post(route('operational-data-reset.challenge'));
        code.value = response.data.code;
        counts.value = response.data.counts;
    } finally {
        loading.value = false;
    }
});

const submit = () => {
    if (!canSubmit.value) return;
    form.confirmation_code = form.confirmation_code.trim().toUpperCase();
    form.slider_confirmed = true;
    form.delete(route('operational-data-reset.destroy'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="max-h-[85dvh] overflow-y-auto p-6">
            <h2 class="text-lg font-semibold text-slate-950">Limpar dados de uso</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Remove movimentações, operações de cartão, importações OFX e análises. Categorias, contas, caixinhas, cartões e configurações estruturais permanecem.</p>

            <div v-if="loading" class="mt-5 text-sm text-slate-500">Preparando confirmação...</div>
            <template v-else-if="code">
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-sm font-semibold text-rose-900">{{ totalRecords }} registro(s) serão removidos</p>
                    <dl class="mt-3 space-y-1 text-sm text-rose-900">
                        <div v-for="(label, key) in countLabels" :key="key" class="flex justify-between gap-4">
                            <dt>{{ label }}</dt>
                            <dd class="font-semibold">{{ counts[key] ?? 0 }}</dd>
                        </div>
                    </dl>
                </div>

                <label for="reset-password" class="mt-5 block text-sm font-medium text-slate-800">Confirme sua senha atual</label>
                <input id="reset-password" v-model="form.password" type="password" autocomplete="current-password" class="mt-2 w-full rounded-xl border-slate-300" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">{{ form.errors.password }}</p>

                <p class="mt-5 text-sm font-medium text-slate-800">Digite exatamente este código:</p>
                <div class="mt-2 rounded-xl bg-rose-50 p-3 text-center font-mono text-xl font-bold tracking-[0.2em] text-rose-800">{{ code }}</div>
                <input v-model="form.confirmation_code" maxlength="10" autocomplete="off" class="mt-3 w-full rounded-xl border-slate-300 font-mono uppercase tracking-widest" aria-label="Código de confirmação da limpeza" />
                <p v-if="form.errors.confirmation_code" class="mt-1 text-xs text-rose-600">{{ form.errors.confirmation_code }}</p>

                <div class="mt-5 rounded-xl border border-slate-200 p-4">
                    <div class="flex justify-between text-sm"><span>Arraste para confirmar</span><strong>{{ slider }}%</strong></div>
                    <input v-model.number="slider" type="range" min="0" max="100" step="1" class="mt-3 w-full accent-rose-600" aria-label="Arraste até 100% para confirmar a limpeza" />
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="min-h-11 rounded-xl border px-4 py-2 text-sm font-semibold" @click="emit('close')">Cancelar</button>
                    <button type="button" :disabled="!canSubmit" class="min-h-11 rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" @click="submit">Limpar {{ totalRecords }} registro(s)</button>
                </div>
            </template>
        </div>
    </Modal>
</template>
