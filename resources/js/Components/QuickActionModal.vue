<script setup>
import Modal from '@/Components/Modal.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowRight, ArrowRightLeft, ArrowUpCircle, PiggyBank, WalletCards, X } from '@lucide/vue';

defineProps({ show: Boolean });
defineEmits(['close']);
const actions = [
    { title: 'Criar uma conta', description: 'Cadastre onde seu dinheiro fica guardado.', route: 'accounts.index', query: { create: 1 }, icon: WalletCards, color: 'bg-blue-50 text-blue-700' },
    { title: 'Criar uma caixinha', description: 'Separe um objetivo dentro de uma conta.', route: 'pockets.index', query: { create: 1 }, icon: PiggyBank, color: 'bg-violet-50 text-violet-700' },
    { title: 'Nova receita', description: 'Abra diretamente o formulário de receita.', route: 'ledger-entries.index', query: { create: 'income' }, icon: ArrowUpCircle, color: 'bg-emerald-50 text-emerald-700' },
    { title: 'Nova despesa', description: 'Abra diretamente o formulário de despesa.', route: 'ledger-entries.index', query: { create: 'expense' }, icon: ArrowDownCircle, color: 'bg-rose-50 text-rose-700' },
    { title: 'Transferir saldo', description: 'Mova valores entre contas e caixinhas.', route: 'ledger-entries.index', query: { transfer: 1 }, icon: ArrowRightLeft, color: 'bg-amber-50 text-amber-700' },
];
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Ação rápida</p><h2 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">O que você deseja fazer?</h2><p class="mt-2 text-sm leading-6 text-slate-500">Escolha uma ação para organizar sua estrutura ou registrar uma movimentação.</p></div>
                <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Fechar" @click="$emit('close')"><X :size="20" /></button>
            </div>
            <div class="mt-6 grid gap-3">
                <Link v-for="action in actions" :key="action.route" :href="route(action.route, action.query ?? {})" class="group flex items-center gap-4 rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50" @click="$emit('close')">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="action.color"><component :is="action.icon" :size="21" /></span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-900">{{ action.title }}</span><span class="mt-0.5 block text-sm text-slate-500">{{ action.description }}</span></span>
                    <ArrowRight :size="18" class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-600" />
                </Link>
            </div>
        </div>
    </Modal>
</template>
