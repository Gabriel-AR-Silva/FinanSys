<script setup>
import HelpHint from '@/Components/HelpHint.vue';
import Modal from '@/Components/Modal.vue';
import { CalendarDays, Pencil, PiggyBank, Plus, Target, Trash2, X } from '@lucide/vue';
import { computed, onMounted, reactive, ref } from 'vue';

const data = ref(null);
const loading = ref(true);
const loadError = ref('');
const modalOpen = ref(false);
const editingGoal = ref(null);
const saving = ref(false);
const deletingId = ref(null);
const errors = ref({});
const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = value => money.format(Number(value ?? 0));
const formatDate = value => new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));
const newOperationId = () => crypto.randomUUID();

const form = reactive({
    name: '',
    target_amount: '',
    target_date: '',
    pocket_id: '',
    operation_id: newOperationId(),
});

const summary = computed(() => data.value?.summary ?? {
    count: 0,
    target_total: '0.00',
    reserved_total: '0.00',
    remaining_total: '0.00',
    completed_count: 0,
});

const load = async () => {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await window.axios.get(route('financial-goals.index'));
        data.value = response.data;
    } catch (_) {
        loadError.value = 'Não foi possível carregar suas metas agora.';
    } finally {
        loading.value = false;
    }
};

const resetForm = () => {
    form.name = '';
    form.target_amount = '';
    form.target_date = '';
    form.pocket_id = '';
    form.operation_id = newOperationId();
    errors.value = {};
    editingGoal.value = null;
};

const openCreate = () => {
    resetForm();
    modalOpen.value = true;
};

const openEdit = goal => {
    editingGoal.value = goal;
    form.name = goal.name;
    form.target_amount = goal.target_amount;
    form.target_date = goal.target_date;
    form.pocket_id = goal.pocket_id ?? '';
    form.operation_id = newOperationId();
    errors.value = {};
    modalOpen.value = true;
};

const closeModal = () => {
    if (saving.value) return;
    modalOpen.value = false;
    resetForm();
};

const save = async () => {
    saving.value = true;
    errors.value = {};

    const payload = {
        name: form.name,
        target_amount: form.target_amount,
        target_date: form.target_date,
        pocket_id: form.pocket_id || null,
        ...(editingGoal.value ? {} : { operation_id: form.operation_id }),
    };

    try {
        const response = editingGoal.value
            ? await window.axios.put(route('financial-goals.update', editingGoal.value.id), payload)
            : await window.axios.post(route('financial-goals.store'), payload);

        data.value = response.data;
        modalOpen.value = false;
        resetForm();
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors ?? {};
        } else {
            errors.value = { general: ['Não foi possível salvar a meta.'] };
        }
    } finally {
        saving.value = false;
    }
};

const removeGoal = async goal => {
    if (!window.confirm(`Excluir a meta “${goal.name}”? Isso não mexe na caixinha nem no dinheiro reservado.`)) return;

    deletingId.value = goal.id;
    try {
        const response = await window.axios.delete(route('financial-goals.destroy', goal.id));
        data.value = response.data;
    } finally {
        deletingId.value = null;
    }
};

const statusLabel = status => ({
    active: 'Em andamento',
    completed: 'Concluída',
    due_today: 'Vence hoje',
    overdue: 'Prazo encerrado',
}[status] ?? status);

const progressWidth = value => `${Math.min(100, Math.max(0, Number(value ?? 0)))}%`;

onMounted(() => {
    window.setTimeout(load, 0);
});
</script>

<template>
    <section class="mt-5 space-y-5" aria-labelledby="goals-heading">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h2 id="goals-heading" class="text-xl font-semibold text-slate-950">Metas</h2>
                    <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700">Beta</span>
                </div>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">Acompanhe objetivos sem transformar projeção em dinheiro real. O valor reservado vem de uma caixinha, quando vinculada.</p>
            </div>
            <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" @click="openCreate">
                <Plus :size="17" /> Nova meta
            </button>
        </header>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Carregando metas em segundo plano…</div>
        <div v-else-if="loadError" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ loadError }}</div>

        <template v-else>
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo das metas">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Metas acompanhadas</p><p class="mt-1 text-xl font-semibold text-slate-950">{{ summary.count }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Valor-alvo total</p><p class="mt-1 text-xl font-semibold text-slate-950">{{ formatMoney(summary.target_total) }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Reservado em caixinhas</p><p class="mt-1 text-xl font-semibold text-emerald-700">{{ formatMoney(summary.reserved_total) }}</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">Falta reservar</p><p class="mt-1 text-xl font-semibold text-violet-700">{{ formatMoney(summary.remaining_total) }}</p></article>
            </section>

            <section v-if="data.goals.length" class="grid gap-4 lg:grid-cols-2">
                <article v-for="goal in data.goals" :key="goal.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><Target :size="18" /></span>
                                <div>
                                    <h3 class="truncate font-semibold text-slate-950">{{ goal.name }}</h3>
                                    <p class="text-xs text-slate-500">{{ statusLabel(goal.status) }} · até {{ formatDate(goal.target_date) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" :aria-label="`Editar ${goal.name}`" @click="openEdit(goal)"><Pencil :size="16" /></button>
                            <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40" :disabled="deletingId === goal.id" :aria-label="`Excluir ${goal.name}`" @click="removeGoal(goal)"><Trash2 :size="16" /></button>
                        </div>
                    </div>

                    <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-violet-500" :style="{ width: progressWidth(goal.progress_percentage) }" />
                    </div>
                    <div class="mt-2 flex justify-between gap-3 text-xs text-slate-500">
                        <span>{{ formatMoney(goal.reserved_amount) }} reservado</span>
                        <span>{{ goal.progress_percentage }}%</span>
                    </div>

                    <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-slate-50 p-3"><dt class="text-[11px] text-slate-500">Meta</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ formatMoney(goal.target_amount) }}</dd></div>
                        <div class="rounded-xl bg-slate-50 p-3"><dt class="text-[11px] text-slate-500">Falta</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ formatMoney(goal.remaining_amount) }}</dd></div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="flex items-center gap-1 text-[11px] text-slate-500">Por dia <HelpHint label="">É apenas uma referência para chegar no prazo. O FinanSys não cria aporte nem reduz seu orçamento automaticamente.</HelpHint></dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900">{{ goal.daily_required === null ? '—' : formatMoney(goal.daily_required) }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 flex items-center gap-2 text-xs text-slate-500">
                        <PiggyBank :size="14" />
                        {{ goal.pocket_name ? `Reserva real: ${goal.pocket_name}` : 'Sem caixinha vinculada; nenhum valor é presumido como reservado.' }}
                    </p>
                </article>
            </section>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-700"><Target :size="22" /></span>
                <h3 class="mt-4 font-semibold text-slate-950">Nenhuma meta criada</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Você pode criar uma meta sem caixinha e vincular uma reserva real depois. Criar a meta não movimenta dinheiro.</p>
                <button type="button" class="mt-5 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white" @click="openCreate">Criar primeira meta</button>
            </div>
        </template>

        <Modal :show="modalOpen" max-width="lg" @close="closeModal">
            <form class="p-6 sm:p-7" @submit.prevent="save">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-700">Meta financeira</p><h3 class="mt-1 text-xl font-semibold text-slate-950">{{ editingGoal ? 'Editar meta' : 'Nova meta' }}</h3></div>
                    <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Fechar" @click="closeModal"><X :size="20" /></button>
                </div>

                <div class="mt-6 grid gap-4">
                    <div><label class="text-sm font-medium text-slate-700" for="goal-name">Nome</label><input id="goal-name" v-model="form.name" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500" placeholder="Ex.: Reserva de emergência" /><p v-if="errors.name" class="mt-1 text-xs text-rose-600">{{ errors.name[0] }}</p></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="text-sm font-medium text-slate-700" for="goal-amount">Valor-alvo</label><input id="goal-amount" v-model="form.target_amount" inputmode="decimal" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500" placeholder="3000.00" /><p v-if="errors.target_amount" class="mt-1 text-xs text-rose-600">{{ errors.target_amount[0] }}</p></div>
                        <div><label class="text-sm font-medium text-slate-700" for="goal-date">Data-alvo</label><input id="goal-date" v-model="form.target_date" type="date" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500" /><p v-if="errors.target_date" class="mt-1 text-xs text-rose-600">{{ errors.target_date[0] }}</p></div>
                    </div>
                    <div>
                        <div class="flex items-center gap-1"><label class="text-sm font-medium text-slate-700" for="goal-pocket">Caixinha para acompanhar a reserva</label><HelpHint label="">Opcional. O saldo real da caixinha será usado como “já reservado”. A meta não cria nem transfere dinheiro.</HelpHint></div>
                        <select id="goal-pocket" v-model="form.pocket_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500">
                            <option value="">Sem caixinha vinculada</option>
                            <option v-for="pocket in data?.pockets ?? []" :key="pocket.id" :value="pocket.id">{{ pocket.name }} · {{ formatMoney(pocket.balance) }}</option>
                        </select>
                        <p v-if="errors.pocket_id" class="mt-1 text-xs text-rose-600">{{ errors.pocket_id[0] }}</p>
                    </div>
                    <p class="rounded-xl bg-violet-50 p-3 text-xs leading-5 text-violet-900">A necessidade por dia é só uma referência. Nenhum aporte, transferência ou mudança no orçamento acontece automaticamente.</p>
                    <p v-if="errors.general" class="text-xs text-rose-600">{{ errors.general[0] }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600" @click="closeModal">Cancelar</button>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving">{{ saving ? 'Salvando…' : 'Salvar meta' }}</button>
                </div>
            </form>
        </Modal>
    </section>
</template>
