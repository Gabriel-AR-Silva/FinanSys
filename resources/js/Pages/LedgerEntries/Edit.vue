<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatMoneyInput, normalizeMoneyInput, sanitizeMoneyInput } from '@/Support/money';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    entry: { type: Object, required: true },
    categories: { type: Array, required: true },
});
const today = new Date(new Date().getTime() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const form = useForm({
    category_id: props.entry.category_id,
    amount: formatMoneyInput(props.entry.amount),
    occurred_at: props.entry.occurred_at,
    description: props.entry.description ?? '',
    planning_type: props.entry.planning_type ?? '',
});
const submit = () => form.transform((data) => ({
    ...data,
    amount: normalizeMoneyInput(data.amount),
    planning_type: props.entry.type === 'expense' ? data.planning_type : undefined,
})).patch(route('ledger-entries.correction.update', props.entry.id), { preserveScroll: true });
</script>

<template>
    <Head title="Corrigir lançamento" />
    <AuthenticatedLayout>
        <main class="mx-auto max-w-2xl space-y-6">
            <header class="space-y-2"><Link :href="route('ledger-entries.index')" class="text-sm font-semibold text-emerald-700 hover:underline">← Voltar aos lançamentos</Link><h1 class="text-2xl font-semibold text-slate-950">Corrigir {{ entry.type === 'income' ? 'receita' : 'despesa' }}</h1><p class="text-sm text-slate-600">A alteração preserva o registro original no histórico de auditoria. Movimentações vinculadas a OFX, previsões, estornos ou reembolsos exigem seu próprio fluxo e não podem ser alteradas aqui.</p></header>
            <form class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submit">
                <InputError :message="form.errors.ledger_entry" />
                <div><InputLabel for="correction-category" value="Categoria" /><select id="correction-category" v-model="form.category_id" required class="mt-2 block w-full rounded-xl border-slate-300"><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><InputError :message="form.errors.category_id" /></div>
                <div><InputLabel for="correction-description" value="Descrição" /><TextInput id="correction-description" v-model="form.description" maxlength="255" class="mt-2 block w-full" /><InputError :message="form.errors.description" /></div>
                <div v-if="entry.type === 'expense'"><InputLabel for="correction-planning" value="Tipo de planejamento" /><select id="correction-planning" v-model="form.planning_type" required class="mt-2 block w-full rounded-xl border-slate-300"><option value="ordinary">Cotidiano</option><option value="fixed">Fixo</option><option value="extraordinary">Extraordinário</option></select><InputError :message="form.errors.planning_type" /></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><InputLabel for="correction-amount" value="Valor (R$)" /><TextInput id="correction-amount" v-model="form.amount" inputmode="decimal" required class="mt-2 block w-full" @input="form.amount = sanitizeMoneyInput($event.target.value)" @blur="form.amount = formatMoneyInput(form.amount)" /><InputError :message="form.errors.amount" /></div><div><InputLabel for="correction-date" value="Data da movimentação" /><TextInput id="correction-date" v-model="form.occurred_at" type="date" :max="today" required class="mt-2 block w-full" /><InputError :message="form.errors.occurred_at" /></div></div>
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><Link :href="route('ledger-entries.index')" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600">Cancelar</Link><button type="submit" :disabled="form.processing" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Salvando…' : 'Salvar correção' }}</button></div>
            </form>
        </main>
    </AuthenticatedLayout>
</template>
