<script setup>
import FinancialSettingsReceiptTab from '@/Components/FinancialSettingsReceiptTab.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatMoneyInput, normalizeMoneyInput, sanitizeMoneyInput } from '@/Support/money';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    month: { type: String, required: true },
    settings: { type: Object, required: true },
    categories: { type: Array, required: true },
    activeTab: { type: String, default: 'protection' },
    receiptForecasts: { type: Object, required: true },
    receiptCategories: { type: Array, required: true },
    availableReceipts: { type: Array, required: true },
});
const initialData = () => ({
    month: props.month,
    protection_type: props.settings.protection_type,
    protection_value: formatMoneyInput(props.settings.protection_value),
    version: props.settings.version,
    essentials: props.settings.essentials.map((item) => ({ category_id: item.category_id, amount: formatMoneyInput(item.amount) })),
});
const form = useForm(initialData());
const tab = ref(props.activeTab);
const selectedMonth = ref(props.month);
const changingMonth = ref(false);
const categoryModalOpen = ref(false);
const categoryForm = useForm({ month: props.month, name: '' });
const tabs = [
    { key: 'protection', title: 'Proteção financeira' },
    { key: 'essentials', title: 'Gastos essenciais' },
    { key: 'receipts', title: 'Recebimentos previstos' },
];
const availableCategories = computed(() => props.categories.filter((category) => category.status === 'active' && !form.essentials.some((item) => item.category_id === category.id)));
const monthLabel = computed(() => new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${props.month}-01T00:00:00Z`)));
watch(() => props.activeTab, (newTab) => { tab.value = newTab; });

function changeTab(nextTab) {
    if (nextTab === tab.value || form.processing || categoryForm.processing || changingMonth.value) return;
    if (nextTab === 'receipts' && form.isDirty) {
        if (!window.confirm('Há alterações no planejamento não salvas. Descartá-las e abrir recebimentos?')) return;
        form.reset();
        form.clearErrors();
    }
    tab.value = nextTab;
    router.get(route('financial-settings.edit'), { month: props.month, tab: nextTab }, {
        preserveState: true, preserveScroll: true, replace: true,
    });
}
function changeMonth(event) {
    const month = event.target.value;
    if (!month || month === props.month) {
        selectedMonth.value = props.month;
        event.target.value = props.month;
        return;
    }
    if (form.isDirty && !window.confirm('Trocar o mês descartará as alterações não salvas. Deseja continuar?')) {
        selectedMonth.value = props.month;
        event.target.value = props.month;
        return;
    }
    changingMonth.value = true;
    router.get(route('financial-settings.edit'), { month, tab: tab.value }, {
        preserveState: false,
        onFinish: () => { changingMonth.value = false; selectedMonth.value = props.month; },
    });
}
function submit() {
    form.transform((data) => ({
        ...data,
        protection_value: normalizeMoneyInput(data.protection_value),
        essentials: data.essentials.map((item) => ({ ...item, amount: normalizeMoneyInput(item.amount) })),
    })).put(route('financial-settings.update', { tab: tab.value }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { form.defaults(initialData()); form.reset(); },
    });
}
function addEssential(categoryId = availableCategories.value[0]?.id) {
    if (categoryId === undefined) return;
    form.essentials.push({ category_id: categoryId, amount: '0,00' });
    form.clearErrors();
}
function categoriesFor(item) {
    return props.categories.filter((category) => category.id === item.category_id || (category.status === 'active' && !form.essentials.some((entry) => entry.category_id === category.id)));
}
function removeEssential(index) {
    form.essentials.splice(index, 1);
    form.clearErrors();
}
function closeCategoryModal() {
    if (categoryForm.processing) return;
    categoryModalOpen.value = false;
    categoryForm.reset();
    categoryForm.clearErrors();
}
function createCategory() {
    const existingIds = new Set(props.categories.map((category) => category.id));
    categoryForm.post(route('financial-settings.categories.store'), {
        preserveState: true, preserveScroll: true,
        onSuccess: (page) => {
            const created = page.props.categories.find((category) => !existingIds.has(category.id) && category.status === 'active');
            if (created) addEssential(created.id);
            categoryModalOpen.value = false;
            categoryForm.reset();
            categoryForm.clearErrors();
        },
    });
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Configuração financeira" />
        <div class="mx-auto flex max-w-3xl flex-col gap-5">
            <header class="space-y-2">
                <p class="text-sm font-medium text-emerald-700">Planejamento mensal</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Configuração financeira</h1>
                <p class="text-sm leading-6 text-slate-500">Organize sua proteção, seus essenciais e o que espera receber, sem misturar previsão com saldo bancário.</p>
            </header>

            <nav aria-label="Seções da configuração financeira" class="grid grid-cols-1 gap-2 rounded-2xl border border-slate-200 bg-white p-2 sm:grid-cols-3" role="tablist">
                <button v-for="option in tabs" :id="`settings-tab-${option.key}`" :key="option.key" type="button" role="tab" :aria-selected="tab === option.key" :aria-controls="`settings-panel-${option.key}`" :tabindex="tab === option.key ? 0 : -1" class="min-w-0 rounded-xl px-3 py-3 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600" :class="tab === option.key ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50'" @click="changeTab(option.key)" @keydown.left.prevent="changeTab(tabs[(tabs.findIndex(item => item.key === option.key) + tabs.length - 1) % tabs.length].key)" @keydown.right.prevent="changeTab(tabs[(tabs.findIndex(item => item.key === option.key) + 1) % tabs.length].key)">{{ option.title }}</button>
            </nav>

            <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5" aria-label="Mês do planejamento">
                <div class="space-y-1"><InputLabel for="settings-month" value="Mês do planejamento" /><p class="text-sm text-slate-500">O período se aplica às três abas.</p></div>
                <input id="settings-month" v-model="selectedMonth" type="month" min="1000-01" max="9999-12" required class="block w-full min-w-0 rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 sm:w-48" :disabled="form.processing || changingMonth || categoryForm.processing" @change="changeMonth" />
            </section>

            <form v-if="tab !== 'receipts'" class="flex flex-col gap-5" @submit.prevent="submit">
                <InputError :message="form.errors.month" />
                <div v-if="form.errors.version" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ form.errors.version }} Recarregue a página antes de salvar.</div>
                <fieldset :disabled="form.processing || changingMonth" class="min-w-0 disabled:opacity-70">
                    <section v-if="tab === 'protection'" id="settings-panel-protection" role="tabpanel" aria-labelledby="settings-tab-protection" class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="flex items-start gap-3"><ShieldCheck :size="22" class="shrink-0 text-emerald-700" /><div><h2 class="font-semibold">Proteção financeira</h2><p class="mt-1 text-sm leading-6 text-slate-500">Escolha um valor fixo ou percentual para planejar sua proteção. Zero é permitido.</p></div></div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2"><InputLabel for="protection-type" value="Tipo de proteção" /><select id="protection-type" v-model="form.protection_type" class="w-full rounded-xl border-slate-300 text-sm" @change="form.protection_value = '0,00'"><option value="fixed">Valor fixo (R$)</option><option value="percentage">Percentual (%)</option></select><InputError :message="form.errors.protection_type" /></div>
                            <div class="space-y-2"><InputLabel for="protection-value" :value="form.protection_type === 'fixed' ? 'Valor de proteção (R$)' : 'Percentual de proteção (%)'" /><TextInput id="protection-value" :model-value="form.protection_value" inputmode="decimal" required class="block w-full" :aria-invalid="!!form.errors.protection_value" @update:model-value="form.protection_value = sanitizeMoneyInput($event)" /><InputError :message="form.errors.protection_value" /></div>
                        </div>
                    </section>
                    <section v-else id="settings-panel-essentials" role="tabpanel" aria-labelledby="settings-tab-essentials" class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div><h2 class="font-semibold">Gastos essenciais previstos</h2><p class="mt-1 text-sm leading-6 text-slate-500">Reserve uma quantia por categoria. A reserva não cria lançamento nem diminui o saldo.</p></div>
                        <p v-if="!form.essentials.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Nenhum essencial previsto neste mês. Adicione uma categoria para começar.</p>
                        <InputError :message="form.errors.essentials" />
                        <div v-for="(item, index) in form.essentials" :key="index" class="grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <div class="min-w-0 space-y-2"><InputLabel :for="`essential-category-${index}`" value="Categoria" /><select :id="`essential-category-${index}`" v-model="item.category_id" required class="w-full min-w-0 rounded-xl border-slate-300 text-sm"><option v-for="category in categoriesFor(item)" :key="category.id" :value="category.id">{{ category.name }}{{ category.status === 'inactive' ? ' (inativa)' : '' }}</option></select><InputError :message="form.errors[`essentials.${index}.category_id`]" /></div>
                            <div class="min-w-0 space-y-2"><InputLabel :for="`essential-amount-${index}`" value="Valor previsto (R$)" /><TextInput :id="`essential-amount-${index}`" :model-value="item.amount" inputmode="decimal" required class="block w-full" @update:model-value="item.amount = sanitizeMoneyInput($event)" /><InputError :message="form.errors[`essentials.${index}.amount`]" /></div>
                            <button type="button" class="flex min-h-10 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm text-rose-700 hover:bg-rose-50 sm:mt-7" :aria-label="`Remover essencial ${index + 1}`" @click="removeEssential(index)"><Trash2 :size="17" /><span class="sm:sr-only">Remover</span></button>
                        </div>
                        <div class="flex flex-wrap gap-2"><button type="button" :disabled="!availableCategories.length" class="flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold disabled:opacity-50" @click="addEssential()"><Plus :size="17" />Adicionar essencial</button><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50" @click="categoryModalOpen = true">Criar categoria de despesa</button></div>
                    </section>
                </fieldset>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p role="status" class="text-sm text-slate-500">{{ form.recentlySuccessful ? 'Configuração salva.' : settings.configured ? `Editando ${monthLabel}.` : 'Este mês ainda não foi configurado.' }}</p><button type="submit" :disabled="form.processing || changingMonth" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Salvando…' : 'Salvar configuração do mês' }}</button></div>
            </form>

            <div v-else id="settings-panel-receipts" role="tabpanel" aria-labelledby="settings-tab-receipts">
                <FinancialSettingsReceiptTab :month="month" :forecasts="receiptForecasts" :categories="receiptCategories" :available-receipts="availableReceipts" />
            </div>
        </div>

        <Modal :show="categoryModalOpen" max-width="md" :closeable="!categoryForm.processing" @close="closeCategoryModal">
            <form class="flex flex-col gap-5 p-6" @submit.prevent="createCategory">
                <div><h2 class="text-lg font-semibold text-slate-950">Criar categoria de despesa</h2><p class="mt-1 text-sm text-slate-500">A categoria será selecionada no planejamento. Os valores preenchidos serão mantidos.</p></div>
                <div class="space-y-2"><InputLabel for="new-expense-category" value="Nome da categoria" /><TextInput id="new-expense-category" v-model="categoryForm.name" required maxlength="255" autofocus class="block w-full" /><InputError :message="categoryForm.errors.name" /><InputError :message="categoryForm.errors.month" /></div>
                <div class="flex justify-end gap-2"><button type="button" :disabled="categoryForm.processing" class="rounded-xl border px-4 py-2" @click="closeCategoryModal">Cancelar</button><button type="submit" :disabled="categoryForm.processing" class="rounded-xl bg-slate-950 px-4 py-2 font-semibold text-white">Criar categoria</button></div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
