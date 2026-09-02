<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import QuickActionModal from '@/Components/QuickActionModal.vue';
import ToastHost from '@/Components/ToastHost.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowLeftRight, ArrowRightLeft, ArrowUpCircle, ChevronDown, LayoutDashboard, Menu, PanelLeftClose, PanelLeftOpen, PiggyBank, Plus, ReceiptText, Tags, UserRound, WalletCards, X } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';

const mobileNavigationOpen = ref(false);
const quickActionOpen = ref(false);
const sidebarCollapsed = ref(false);
const navigation = [
    { label: 'Visão geral', route: 'dashboard', icon: LayoutDashboard },
    { label: 'Contas', route: 'accounts.index', icon: WalletCards },
    { label: 'Caixinhas', route: 'pockets.index', icon: PiggyBank },
    { label: 'Lançamentos', route: 'ledger-entries.index', icon: ReceiptText },
    { label: 'Categorias', route: 'categories.index', icon: Tags },
];
const isActive = (routeName) => route().current(routeName);

onMounted(() => {
    sidebarCollapsed.value = localStorage.getItem('finansys.sidebar-collapsed') === 'true';
});

watch(sidebarCollapsed, (collapsed) => {
    localStorage.setItem('finansys.sidebar-collapsed', String(collapsed));
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <aside class="fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-slate-200 bg-slate-950 py-6 text-white transition-[width,padding] duration-300 lg:flex" :class="sidebarCollapsed ? 'w-20 px-3' : 'w-72 px-5'">
            <button type="button" class="absolute -right-3 top-8 flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-950" :aria-label="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" @click="sidebarCollapsed = !sidebarCollapsed">
                <PanelLeftOpen v-if="sidebarCollapsed" :size="15" />
                <PanelLeftClose v-else :size="15" />
            </button>

            <Link :href="route('dashboard')" class="flex items-center gap-3" :class="sidebarCollapsed ? 'justify-center px-0' : 'px-2'" title="FinanSys">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-950/20"><ArrowLeftRight :size="23" :stroke-width="2.4" /></span>
                <span v-show="!sidebarCollapsed"><span class="block text-lg font-semibold tracking-tight">FinanSys</span><span class="block text-xs text-slate-400">Finanças pessoais</span></span>
            </Link>

            <nav class="mt-10 flex flex-1 flex-col gap-2" aria-label="Navegação principal">
                <Link v-for="item in navigation" :key="item.route" :href="route(item.route)" class="group relative flex items-center rounded-xl py-3 text-sm font-medium transition" :class="[isActive(item.route) ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-300 hover:bg-white/10 hover:text-white', sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3']" :aria-label="item.label">
                    <component :is="item.icon" :size="20" class="shrink-0" /><span v-show="!sidebarCollapsed">{{ item.label }}</span>
                    <span v-if="sidebarCollapsed" class="pointer-events-none absolute left-full z-50 ml-3 whitespace-nowrap rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium text-white opacity-0 shadow-xl transition group-hover:opacity-100 group-focus-visible:opacity-100">{{ item.label }}</span>
                </Link>
            </nav>

            <button type="button" class="group relative flex w-full items-center justify-center rounded-xl bg-emerald-400 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-slate-950" :class="sidebarCollapsed ? 'px-0' : 'gap-2 px-4'" aria-label="Nova movimentação" @click="quickActionOpen = true">
                <Plus :size="19" /><span v-show="!sidebarCollapsed">Nova movimentação</span>
                <span v-if="sidebarCollapsed" class="pointer-events-none absolute left-full z-50 ml-3 whitespace-nowrap rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium text-white opacity-0 shadow-xl transition group-hover:opacity-100 group-focus-visible:opacity-100">Nova movimentação</span>
            </button>

            <div class="mt-5 border-t border-white/10 pt-5">
                <Dropdown align="right" width="56">
                    <template #trigger>
                        <button type="button" class="group relative flex w-full items-center rounded-xl p-2 text-left transition hover:bg-white/10" :class="sidebarCollapsed ? 'justify-center' : 'gap-3'" aria-label="Menu do usuário">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-sm font-semibold text-emerald-300">{{ $page.props.auth.user.name.slice(0, 1).toUpperCase() }}</span>
                            <span v-show="!sidebarCollapsed" class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $page.props.auth.user.name }}</span><span class="block truncate text-xs text-slate-400">{{ $page.props.auth.user.email }}</span></span>
                            <ChevronDown v-show="!sidebarCollapsed" :size="16" class="text-slate-400" />
                            <span v-if="sidebarCollapsed" class="pointer-events-none absolute left-full z-50 ml-3 whitespace-nowrap rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium text-white opacity-0 shadow-xl transition group-hover:opacity-100 group-focus-visible:opacity-100">Perfil de {{ $page.props.auth.user.name }}</span>
                        </button>
                    </template>
                    <template #content><DropdownLink :href="route('profile.edit')">Meu perfil</DropdownLink><DropdownLink :href="route('logout')" method="post" as="button">Sair</DropdownLink></template>
                </Dropdown>
            </div>
        </aside>

        <div class="transition-[padding] duration-300" :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-10">
                    <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Abrir navegação" @click="mobileNavigationOpen = true"><Menu :size="23" /></button>
                    <div class="hidden items-center gap-2 lg:flex"><Link :href="route('ledger-entries.index', { create: 'income' })" class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"><ArrowUpCircle :size="17" />Receita</Link><Link :href="route('ledger-entries.index', { create: 'expense' })" class="flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"><ArrowDownCircle :size="17" />Despesa</Link><Link :href="route('ledger-entries.index', { transfer: 1 })" class="flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100"><ArrowRightLeft :size="17" />Transferir</Link></div>
                    <button type="button" class="flex items-center gap-2 rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 lg:hidden" @click="quickActionOpen = true"><Plus :size="18" />Adicionar</button>
                    <Link :href="route('profile.edit')" class="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 sm:flex lg:ml-auto"><UserRound :size="18" />{{ $page.props.auth.user.name }}</Link>
                </div>
            </header>
            <main class="px-4 py-7 sm:px-6 sm:py-9 lg:px-10 lg:py-10"><div class="mx-auto max-w-7xl"><slot /></div></main>
        </div>

        <div v-if="mobileNavigationOpen" class="fixed inset-0 z-40 lg:hidden">
            <button class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" aria-label="Fechar navegação" @click="mobileNavigationOpen = false" />
            <aside class="relative flex h-full w-[min(86vw,22rem)] flex-col bg-slate-950 px-5 py-5 text-white shadow-2xl">
                <div class="flex items-center justify-between gap-4">
                    <Link :href="route('dashboard')" class="flex items-center gap-3" @click="mobileNavigationOpen = false"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-400 text-slate-950"><ArrowLeftRight :size="21" /></span><span class="font-semibold">FinanSys</span></Link>
                    <button class="rounded-lg p-2 text-slate-300 hover:bg-white/10" aria-label="Fechar navegação" @click="mobileNavigationOpen = false"><X :size="22" /></button>
                </div>
                <nav class="mt-8 flex flex-1 flex-col gap-2">
                    <Link v-for="item in navigation" :key="item.route" :href="route(item.route)" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium" :class="isActive(item.route) ? 'bg-white text-slate-950' : 'text-slate-300'" @click="mobileNavigationOpen = false"><component :is="item.icon" :size="20" />{{ item.label }}</Link>
                </nav>
                <Link :href="route('profile.edit')" class="flex items-center gap-3 border-t border-white/10 px-3 pt-5 text-sm text-slate-300"><UserRound :size="19" />Meu perfil</Link>
            </aside>
        </div>

        <QuickActionModal :show="quickActionOpen" @close="quickActionOpen = false" />
        <ToastHost />
    </div>
</template>
