<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);
const code = ref('');
const slider = ref(0);
const loading = ref(false);
const form = useForm({ confirmation_code: '', slider_confirmed: false });

const matches = computed(() => form.confirmation_code.trim().toUpperCase() === code.value);
const canSubmit = computed(() => matches.value && slider.value === 100 && !form.processing);

watch(() => props.show, async (show) => {
    if (!show) return;
    code.value = '';
    slider.value = 0;
    form.reset();
    loading.value = true;
    try {
        const response = await axios.post(route('operational-data-reset.challenge'));
        code.value = response.data.code;
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
        <div class="p-6">
            <h2 class="text-lg font-semibold text-slate-950">Limpar dados de uso</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Remove movimentações, operações de cartão, importações OFX e análises. Categorias, contas, caixinhas, cartões e configurações estruturais permanecem.</p>

            <div v-if="loading" class="mt-5 text-sm text-slate-500">Preparando confirmação...</div>
            <template v-else-if="code">
                <p class="mt-5 text-sm font-medium text-slate-800">Digite exatamente este código:</p>
                <div class="mt-2 rounded-xl bg-rose-50 p-3 text-center font-mono text-xl font-bold tracking-[0.2em] text-rose-800">{{ code }}</div>
                <input v-model="form.confirmation_code" maxlength="10" autocomplete="off" class="mt-3 w-full rounded-xl border-slate-300 font-mono uppercase tracking-widest" />
                <p v-if="form.errors.confirmation_code" class="mt-1 text-xs text-rose-600">{{ form.errors.confirmation_code }}</p>

                <div class="mt-5 rounded-xl border border-slate-200 p-4">
                    <div class="flex justify-between text-sm"><span>Arraste para confirmar</span><strong>{{ slider }}%</strong></div>
                    <input v-model.number="slider" type="range" min="0" max="100" step="1" class="mt-3 w-full accent-rose-600" aria-label="Arraste até 100% para confirmar a limpeza" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-xl border px-4 py-2 text-sm font-semibold" @click="emit('close')">Cancelar</button>
                    <button type="button" :disabled="!canSubmit" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" @click="submit">Limpar dados de uso</button>
                </div>
            </template>
        </div>
    </Modal>
</template>
