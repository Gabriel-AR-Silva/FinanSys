<script setup>
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);
const code = ref('');
const groups = ref([]);
const dependencies = ref({});
const defaults = ref([]);
const slider = ref(0);
const loading = ref(false);
const form = useForm({ password: '', confirmation_code: '', slider_confirmed: false, selected_groups: [] });

const selectedSet = computed(() => new Set(form.selected_groups));
const selectedGroups = computed(() => groups.value.filter((group) => selectedSet.value.has(group.key)));
const totalRecords = computed(() => selectedGroups.value.reduce((total, group) => total + Number(group.count ?? 0), 0));
const matches = computed(() => form.confirmation_code.trim().toUpperCase() === code.value);
const canSubmit = computed(() => form.selected_groups.length > 0 && matches.value && form.password.length > 0 && slider.value === 100 && !form.processing);

const normalize = (selection) => {
    const allowed = new Set(groups.value.map((group) => group.key));
    const resolved = new Set(selection.filter((group) => allowed.has(group)));
    let changed = true;

    while (changed) {
        changed = false;
        for (const group of [...resolved]) {
            for (const dependency of dependencies.value[group] ?? []) {
                if (allowed.has(dependency) && !resolved.has(dependency)) {
                    resolved.add(dependency);
                    changed = true;
                }
            }
        }
    }

    return groups.value.map((group) => group.key).filter((key) => resolved.has(key));
};

const selectAll = () => {
    form.selected_groups = [...defaults.value];
};

const preserveCategories = () => {
    form.selected_groups = normalize(defaults.value.filter((group) => group !== 'categories'));
};

const toggleGroup = (key) => {
    const next = selectedSet.value.has(key)
        ? form.selected_groups.filter((group) => group !== key)
        : [...form.selected_groups, key];
    form.selected_groups = normalize(next);
};

watch(() => props.show, async (show) => {
    if (!show) return;
    code.value = '';
    groups.value = [];
    dependencies.value = {};
    defaults.value = [];
    slider.value = 0;
    form.reset();
    form.clearErrors();
    loading.value = true;
    try {
        const response = await axios.post(route('operational-data-reset.challenge'));
        code.value = response.data.code;
        groups.value = response.data.groups ?? [];
        dependencies.value = response.data.dependencies ?? {};
        defaults.value = response.data.default_selected_groups ?? groups.value.map((group) => group.key);
        form.selected_groups = normalize([...defaults.value]);
    } finally {
        loading.value = false;
    }
});

const submit = () => {
    if (!canSubmit.value) return;
    form.confirmation_code = form.confirmation_code.trim().toUpperCase();
    form.slider_confirmed = true;
    form.selected_groups = normalize(form.selected_groups);
    form.delete(route('operational-data-reset.destroy'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="max-h-[85dvh] overflow-y-auto p-6">
            <h2 class="text-lg font-semibold text-slate-950">Limpar dados de uso</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Tudo começa selecionado. Desmarque o que deseja manter; dependências necessárias são selecionadas automaticamente.</p>

            <div v-if="loading" class="mt-5 text-sm text-slate-500">Preparando confirmação...</div>
            <template v-else-if="code">
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold" @click="selectAll">Selecionar tudo</button>
                    <button type="button" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800" @click="preserveCategories">Excluir tudo, mas manter categorias</button>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm font-semibold text-slate-900">Dados selecionados</p>
                        <p class="text-xs text-slate-500">{{ selectedGroups.length }} grupo(s) · {{ totalRecords }} registro(s)</p>
                    </div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <label v-for="group in groups" :key="group.key" class="flex cursor-pointer gap-3 rounded-xl border bg-white p-3" :class="selectedSet.has(group.key) ? 'border-rose-300' : 'border-slate-200'">
                            <input type="checkbox" class="mt-1 rounded border-slate-300 text-rose-700" :checked="selectedSet.has(group.key)" @change="toggleGroup(group.key)" />
                            <span class="min-w-0 flex-1">
                                <span class="flex justify-between gap-3 text-sm font-semibold text-slate-900"><span>{{ group.label }}</span><span>{{ group.count }}</span></span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">{{ group.description }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="form.errors.selected_groups" class="mt-2 text-xs text-rose-600">{{ form.errors.selected_groups }}</p>
                    <p class="mt-3 text-xs text-slate-500">Pix no Crédito está incluído em Operações de cartão.</p>
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
                    <button type="button" :disabled="!canSubmit" class="min-h-11 rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" @click="submit">Excluir {{ totalRecords }} registro(s)</button>
                </div>
            </template>
        </div>
    </Modal>
</template>
