<script setup>
import QuickActionModal from '@/Components/QuickActionModal.vue';
import ToastHost from '@/Components/ToastHost.vue';
import TsukiOnboarding from '@/Components/TsukiOnboarding.vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowRightLeft, ArrowUpCircle, BellRing, CreditCard, FileUp, LayoutDashboard, LogOut, Menu, PanelLeftClose, PanelLeftOpen, PiggyBank, Plus, ReceiptText, RotateCcw, Settings, Tags, UserRound, WalletCards, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const mobileNavigationOpen = ref(false);
const mobileNavigationTrigger = ref(null);
const quickActionOpen = ref(false);
const sidebarCollapsed = ref(false);
const navigation = [
    { label: 'Visão geral', route: 'dashboard', icon: LayoutDashboard },
    { label: 'Contas', route: 'accounts.index', icon: WalletCards },
    { label: 'Caixinhas', route: 'pockets.index', icon: PiggyBank },
    { label: 'Lançamentos', route: 'ledger-entries.index', icon: ReceiptText },
    { label: 'Importar OFX', route: 'ofx-imports.index', icon: FileUp },
    { label: 'Cartões', route: 'credit-cards.index', icon: CreditCard },
    { label: 'Correções de cartão', route: 'card-corrections.index', icon: RotateCcw },
    { label: 'Categorias', route: 'categories.index', icon: Tags },
    { label: 'Configuração financeira', route: 'financial-settings.edit', icon: Settings },
    { label: 'Avisos financeiros', route: 'internal-alerts.index', icon: BellRing },
];
const userAvatar = computed(() => $page.props.auth.user.avatar_path ? `/storage/${$page.props.auth.user.avatar_path}` : null);
const userInitials = computed(() => ($page.props.auth.user.name || 'U').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join(''));
const isActive = (routeName) => route().current(routeName);
let previousBodyOverflow = '';
const openMobileNavigation = () => { mobileNavigationOpen.value = true; };
const closeMobileNavigation = (restoreFocus = true) => { mobileNavigationOpen.value = false; if (restoreFocus) nextTick(() => mobileNavigationTrigger.value?.focus()); };
const logout = () => router.post(route('logout'), {}, { preserveScroll: false, replace: true, onSuccess: () => closeMobileNavigation(false) });
const closeMobileNavigationOnEscape = (event) => { if (event.key === 'Escape' && mobileNavigationOpen.value) closeMobileNavigation(); };
onMounted(() => { sidebarCollapsed.value = localStorage.getItem('finansys.sidebar-collapsed') === 'true'; window.addEventListener('keydown', closeMobileNavigationOnEscape); });
onUnmounted(() => { window.removeEventListener('keydown', closeMobileNavigationOnEscape); document.body.style.overflow = previousBodyOverflow; });
watch(sidebarCollapsed, (collapsed) => localStorage.setItem('finansys.sidebar-collapsed', String(collapsed)));
watch(mobileNavigationOpen, (open) => { if (open) { previousBodyOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; } else document.body.style.overflow = previousBodyOverflow; });
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <aside class="fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-slate-200 bg-slate-950 py-6 text-white transition-[width,padding] duration-300 lg:flex" :class="sidebarCollapsed ? 'w-20 px-3' : 'w-72 px-5'">
            <button type="button" class="absolute -right-3 top-8 flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-950" :aria-label="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" @click="sidebarCollapsed = !sidebarCollapsed"><PanelLeftOpen v-if="sidebarCollapsed" :size="15" /><PanelLeftClose v-else :size="15" /></button>
            <Link :href="route('dashboard')" class="flex items-center gap-3" :class="sidebarCollapsed ? 'justify-center px-0' : 'px-2'" title="FinanSys"><img src="/finansys-icon.svg" alt="" width="44" height="44" class="h-11 w-11 shrink-0 rounded-2xl object-contain shadow-lg shadow-emerald-950/20" /><span v-show="!sidebarCollapsed"><span class="block text-lg font-semibold tracking-tight">FinanSys</span><span class="block text-xs text-slate-400">Finanças pessoais</span></span></Link>
            <nav class="mt-10 flex flex-1 flex-col gap-2" aria-label="Navegação principal"><Link v-for="item in navigation" :key="item.route" :href="route(item.route)" class="group relative flex items-center rounded-xl py-3 text-sm font-medium transition" :class="[isActive(item.route) ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-300 hover:bg-white/10 hover:text-white', sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-3']" :aria-label="item.label"><component :is="item.icon" :size="20" class="shrink-0" /><span v-show="!sidebarCollapsed">{{ item.label }}</span><span v-if="sidebarCollapsed" class="pointer-events-none absolute left-full z-50 ml-3 whitespace-nowrap rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium text-white opacity-0 shadow-xl transition group-hover:opacity-100 group-focus-visible:opacity-100">{{ item.label }}</span></Link></nav>
            <button type="button" class="group relative flex w-full items-center justify-center rounded-xl bg-emerald-400 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-slate-950" :class="sidebarCollapsed ? 'px-0' : 'gap-2 px-4'" aria-label="Nova movimentação" @click="quickActionOpen = true"><Plus :size="19" /><span v-show="!sidebarCollapsed">Nova movimentação</span></button>
            <div class="mt-5 flex flex-col gap-1 border-t border-white/10 pt-5">
                <Link :href="route('profile.edit')" class="group relative flex w-full items-center rounded-xl p-2 text-left transition hover:bg-white/10" :class="sidebarCollapsed ? 'justify-center' : 'gap-3'" aria-label="Meu perfil"><span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-800 text-xs font-semibold text-emerald-300 ring-1 ring-white/10"><img v-if="userAvatar" :src="userAvatar" alt="" class="h-full w-full object-cover" /><span v-else>{{ userInitials }}</span></span><span v-show="!sidebarCollapsed" class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $page.props.auth.user.name }}</span><span class="block truncate text-xs text-slate-400">{{ $page.props.auth.user.email }}</span></span></Link>
                <button type="button" class="group relative flex w-full items-center rounded-xl p-2 text-sm font-medium text-rose-300 transition hover:bg-rose-400/10 hover:text-rose-200" :class="sidebarCollapsed ? 'justify-center' : 'gap-3 px-3'" aria-label="Sair do FinanSys" @click="logout"><LogOut :size="19" class="shrink-0" /><span v-show="!sidebarCollapsed">Sair</span></button>
            </div>
        </aside>
        <div class="min-w-0 transition-[padding] duration-300" :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur"><div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
                <button ref="mobileNavigationTrigger" type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Abrir navegação" @click="openMobileNavigation"><Menu :size="23" /></button>
                <Link :href="route('dashboard')" class="flex shrink-0 items-center gap-2 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 lg:hidden" aria-label="FinanSys — painel"><img src="/finansys-icon.svg" alt="" width="36" height="36" class="h-9 w-9 rounded-xl object-contain" /><span class="hidden text-sm font-semibold text-slate-950 sm:inline">FinanSys</span></Link>
                <div class="hidden items-center gap-2 lg:flex"><Link :href="route('ledger-entries.index', { create: 'income' })" class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100"><ArrowUpCircle :size="17" />Receita</Link><Link :href="route('ledger-entries.index', { create: 'expense' })" class="flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"><ArrowDownCircle :size="17" />Despesa</Link><Link :href="route('ledger-entries.index', { transfer: 1 })" class="flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-100"><ArrowRightLeft :size="17" />Transferir</Link></div>
                <button type="button" class="flex items-center gap-1.5 rounded-xl bg-slate-950 px-2.5 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 lg:hidden sm:gap-2 sm:px-3.5" @click="quickActionOpen = true"><Plus :size="18" /><span class="hidden min-[370px]:inline">Adicionar</span></button>
                <Link :href="route('profile.edit')" class="hidden items-center gap-2 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 sm:flex lg:ml-auto"><span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-slate-950 text-[11px] font-bold text-emerald-200"><img v-if="userAvatar" :src="userAvatar" alt="" class="h-full w-full object-cover" /><span v-else>{{ userInitials }}</span></span><span class="max-w-40 truncate">{{ $page.props.auth.user.name }}</span></Link>
            </div></header>
            <main class="min-w-0 px-4 py-7 sm:px-6 sm:py-9 lg:px-10 lg:py-10"><div class="mx-auto min-w-0 max-w-7xl"><slot /></div></main>
        </div>
        <div v-if="mobileNavigationOpen" class="fixed inset-0 z-40 lg:hidden"><button class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" aria-label="Fechar navegação" @click="closeMobileNavigation" /><aside class="relative flex h-dvh max-h-dvh w-[min(86vw,22rem)] flex-col overflow-hidden bg-slate-950 px-5 py-5 text-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Menu principal"><div class="flex shrink-0 items-center justify-between gap-4"><Link :href="route('dashboard')" class="flex items-center gap-3" @click="closeMobileNavigation(false)"><img src="/finansys-icon.svg" alt="" width="40" height="40" class="h-10 w-10 rounded-xl object-contain" /><span class="font-semibold">FinanSys</span></Link><button class="rounded-lg p-2 text-slate-300 hover:bg-white/10" aria-label="Fechar navegação" @click="closeMobileNavigation"><X :size="22" /></button></div><nav class="mt-8 flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto" aria-label="Navegação móvel"><Link v-for="item in navigation" :key="item.route" :href="route(item.route)" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium" :class="isActive(item.route) ? 'bg-white text-slate-950' : 'text-slate-300'" @click="closeMobileNavigation(false)"><component :is="item.icon" :size="20" />{{ item.label }}</Link></nav><div class="flex shrink-0 flex-col gap-3 border-t border-white/10 pt-5"><Link :href="route('profile.edit')" class="flex items-center gap-3 px-3 text-sm text-slate-300" @click="closeMobileNavigation(false)"><span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-slate-800 text-[11px] font-bold text-emerald-200"><img v-if="userAvatar" :src="userAvatar" alt="" class="h-full w-full object-cover" /><span v-else>{{ userInitials }}</span></span>Meu perfil</Link><button type="button" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-rose-300 hover:bg-rose-400/10" @click="logout"><LogOut :size="19" />Sair</button></div></aside></div>
        <QuickActionModal :show="quickActionOpen" @close="quickActionOpen = false" />
        <TsukiOnboarding v-if="$page.props.onboarding" :essential-steps="$page.props.onboarding.essentialSteps" :recommended-steps="$page.props.onboarding.recommendedSteps" :progress="$page.props.onboarding.progress" :initially-open="$page.props.onboarding.initiallyOpen" />
        <ToastHost />
    </div>
</template>
