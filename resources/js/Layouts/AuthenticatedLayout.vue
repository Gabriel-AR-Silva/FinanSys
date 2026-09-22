<script setup>
import QuickActionModal from '@/Components/QuickActionModal.vue';
import ToastHost from '@/Components/ToastHost.vue';
import TsukiOnboarding from '@/Components/TsukiOnboarding.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowRightLeft, ArrowUpCircle, BellRing, ChevronDown, CreditCard, FileUp, LayoutDashboard, LogOut, Menu, PanelLeftClose, PanelLeftOpen, PiggyBank, Plus, ReceiptText, RotateCcw, Settings, Tags, WalletCards, Wrench, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const avatar = computed(() => user.value.avatar_path ? `${route('profile.avatar')}?v=${encodeURIComponent(user.value.avatar_path)}` : null);
const avatarFailed = ref(false);
const initials = computed(() => (user.value.name || 'U').trim().split(/\s+/).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join(''));
const mobileNavigationOpen = ref(false);
const mobileNavigationTrigger = ref(null);
const quickActionOpen = ref(false);
const sidebarCollapsed = ref(false);
const toolsOpen = ref(false);
const toolsContainer = ref(null);
let previousBodyOverflow = '';

const sections = [
    { label: 'Início', items: [{ label: 'Visão geral', route: 'dashboard', icon: LayoutDashboard }] },
    { label: 'Finanças', items: [
        { label: 'Contas', route: 'accounts.index', icon: WalletCards },
        { label: 'Caixinhas', route: 'pockets.index', icon: PiggyBank },
        { label: 'Lançamentos', route: 'ledger-entries.index', icon: ReceiptText },
        { label: 'Cartões', route: 'credit-cards.index', icon: CreditCard },
        { label: 'Orçamento diário', route: 'daily-budgets.edit', icon: PiggyBank },
    ] },
    { label: 'Organização', items: [{ label: 'Categorias', route: 'categories.index', icon: Tags }] },
];
const tools = [
    { label: 'Importar extrato OFX', route: 'ofx-imports.index', icon: FileUp },
    { label: 'Correções de cartão', route: 'card-corrections.index', icon: RotateCcw },
    { label: 'Configuração financeira', route: 'financial-settings.edit', icon: Settings },
    { label: 'Avisos financeiros', route: 'internal-alerts.index', icon: BellRing },
];
const isActive = routeName => route().current(routeName);
const closeTools = () => { toolsOpen.value = false; };
const openMobileNavigation = () => { closeTools(); mobileNavigationOpen.value = true; };
const closeMobileNavigation = (restoreFocus = true) => {
    mobileNavigationOpen.value = false;
    if (restoreFocus) nextTick(() => mobileNavigationTrigger.value?.focus());
};
const logout = () => router.post(route('logout'), {}, { preserveScroll: false, replace: true, onSuccess: () => closeMobileNavigation(false) });
const handleKeydown = event => {
    if (event.key !== 'Escape') return;
    if (toolsOpen.value) { closeTools(); return; }
    if (mobileNavigationOpen.value) closeMobileNavigation();
};
const handlePointerDown = event => {
    if (toolsOpen.value && !toolsContainer.value?.contains(event.target)) closeTools();
};
onMounted(() => {
    try { sidebarCollapsed.value = window.localStorage.getItem('finansys.sidebar-collapsed') === 'true'; } catch (_) { /* storage may be blocked */ }
    document.addEventListener('keydown', handleKeydown);
    document.addEventListener('pointerdown', handlePointerDown);
});
onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('pointerdown', handlePointerDown);
    if (mobileNavigationOpen.value) document.body.style.overflow = previousBodyOverflow;
});
watch(avatar, () => { avatarFailed.value = false; });
watch(sidebarCollapsed, collapsed => {
    try { window.localStorage.setItem('finansys.sidebar-collapsed', String(collapsed)); } catch (_) { /* storage may be blocked */ }
});
watch(mobileNavigationOpen, open => {
    if (open) { previousBodyOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; }
    else document.body.style.overflow = previousBodyOverflow;
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <aside class="fixed inset-y-0 left-0 z-30 hidden h-dvh flex-col overflow-hidden border-r border-slate-800 bg-slate-950 py-5 text-white transition-[width,padding] duration-300 lg:flex" :class="sidebarCollapsed ? 'w-20 px-3' : 'w-72 px-5'">
            <button type="button" class="absolute right-2 top-6 flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" :aria-label="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" @click="sidebarCollapsed = !sidebarCollapsed"><PanelLeftOpen v-if="sidebarCollapsed" :size="18" /><PanelLeftClose v-else :size="18" /></button>
            <Link :href="route('dashboard')" class="flex h-12 shrink-0 items-center text-lg font-bold tracking-tight" :class="sidebarCollapsed ? 'justify-start pl-1 text-sm' : 'pl-2'" aria-label="FinanSys — visão geral"><span>Finan<span class="text-emerald-400">Sys</span></span></Link>
            <nav class="mt-6 min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain pb-4 pr-1 [scrollbar-width:thin] [scrollbar-color:#475569_transparent]" aria-label="Navegação principal">
                <section v-for="section in sections" :key="section.label" :aria-label="section.label">
                    <h2 v-if="!sidebarCollapsed" class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-500">{{ section.label }}</h2>
                    <div v-else class="mb-2 border-t border-white/10" aria-hidden="true" />
                    <Link v-for="item in section.items" :key="item.route" :href="route(item.route)" class="group relative mb-1 flex min-h-11 items-center rounded-xl py-2.5 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400" :class="[isActive(item.route) ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10 hover:text-white', sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-3']" :aria-label="item.label" :title="sidebarCollapsed ? item.label : undefined"><component :is="item.icon" :size="20" class="shrink-0" aria-hidden="true" /><span v-if="!sidebarCollapsed">{{ item.label }}</span></Link>
                </section>
            </nav>
            <div class="shrink-0 space-y-3 border-t border-white/10 pt-4">
                <button type="button" class="flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-400 px-2.5 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300" aria-label="Nova movimentação" @click="quickActionOpen = true"><Plus :size="19" /><span v-if="!sidebarCollapsed" class="ml-2">Nova movimentação</span></button>
                <Link :href="route('profile.edit')" class="flex min-h-11 items-center rounded-xl p-2 text-left text-slate-200 hover:bg-white/10" :class="sidebarCollapsed ? 'justify-center' : 'gap-3'" aria-label="Meu perfil" :title="sidebarCollapsed ? 'Meu perfil' : undefined"><span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-800 text-xs font-semibold text-emerald-300"><img v-if="avatar && !avatarFailed" :src="avatar" alt="" class="h-full w-full object-cover" @error="avatarFailed = true" /><span v-else>{{ initials }}</span></span><span v-if="!sidebarCollapsed" class="min-w-0"><span class="block truncate text-sm font-medium">{{ user.name }}</span><span class="block truncate text-xs text-slate-400">{{ user.email }}</span></span></Link>
                <button type="button" class="flex min-h-10 w-full items-center rounded-xl px-3 text-sm font-medium text-rose-300 hover:bg-rose-400/10" :class="sidebarCollapsed ? 'justify-center' : 'gap-3'" aria-label="Sair do FinanSys" @click="logout"><LogOut :size="19" /><span v-if="!sidebarCollapsed">Sair</span></button>
            </div>
        </aside>

        <div class="min-w-0 transition-[padding] duration-300" :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur"><div class="flex h-16 items-center gap-2 px-3 sm:px-6 lg:px-8">
                <button ref="mobileNavigationTrigger" type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Abrir navegação" @click="openMobileNavigation"><Menu :size="23" /></button>
                <Link :href="route('dashboard')" class="text-sm font-bold tracking-tight lg:hidden" aria-label="FinanSys — visão geral">Finan<span class="text-emerald-600">Sys</span></Link>
                <div class="hidden items-center gap-2 lg:flex"><Link :href="route('ledger-entries.index', { create: 'income' })" class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100"><ArrowUpCircle :size="17" />Receita</Link><Link :href="route('ledger-entries.index', { create: 'expense' })" class="flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100"><ArrowDownCircle :size="17" />Despesa</Link><Link :href="route('ledger-entries.index', { transfer: 1 })" class="flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-100"><ArrowRightLeft :size="17" />Transferir</Link></div>
                <button type="button" class="ml-auto flex min-h-10 items-center gap-1 rounded-xl bg-slate-950 px-2.5 text-sm font-semibold text-white hover:bg-slate-800 lg:hidden" aria-label="Nova movimentação" @click="quickActionOpen = true"><Plus :size="18" /><span class="hidden min-[390px]:inline">Adicionar</span></button>
                <div ref="toolsContainer" class="relative lg:ml-auto">
                    <button type="button" class="flex min-h-10 items-center gap-1.5 rounded-xl px-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="Ferramentas e ajustes" :aria-expanded="toolsOpen" aria-controls="finansys-tools-menu" @click="toolsOpen = !toolsOpen"><Wrench :size="18" /><span class="hidden sm:inline">Ferramentas</span><ChevronDown :size="15" aria-hidden="true" /></button>
                    <nav v-if="toolsOpen" id="finansys-tools-menu" class="absolute right-0 top-full z-40 mt-2 w-64 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl" aria-label="Ferramentas e ajustes"><Link v-for="item in tools" :key="item.route" :href="route(item.route)" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" :aria-current="isActive(item.route) ? 'page' : undefined" @click="closeTools"><component :is="item.icon" :size="18" class="shrink-0" aria-hidden="true" />{{ item.label }}</Link></nav>
                </div>
                <Link :href="route('profile.edit')" class="hidden items-center gap-2 rounded-xl px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:flex" aria-label="Meu perfil"><span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-950 text-xs font-semibold text-emerald-300"><img v-if="avatar && !avatarFailed" :src="avatar" alt="" class="h-full w-full object-cover" @error="avatarFailed = true" /><span v-else>{{ initials }}</span></span><span class="hidden max-w-32 truncate xl:inline">{{ user.name }}</span></Link>
            </div></header>
            <main class="min-w-0 px-4 py-7 sm:px-6 sm:py-9 lg:px-10 lg:py-10"><div class="mx-auto min-w-0 max-w-7xl"><slot /></div></main>
        </div>

        <div v-if="mobileNavigationOpen" class="fixed inset-0 z-40 lg:hidden"><button type="button" class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" aria-label="Fechar navegação" @click="closeMobileNavigation" /><aside class="relative flex h-dvh max-h-dvh w-[min(86vw,22rem)] flex-col overflow-hidden bg-slate-950 px-5 py-5 text-white shadow-2xl" role="dialog" aria-modal="true" aria-label="Menu principal">
            <div class="flex shrink-0 items-center justify-between"><Link :href="route('dashboard')" class="text-lg font-bold" @click="closeMobileNavigation(false)">Finan<span class="text-emerald-400">Sys</span></Link><button type="button" class="rounded-lg p-2 text-slate-300 hover:bg-white/10" aria-label="Fechar navegação" @click="closeMobileNavigation"><X :size="22" /></button></div>
            <nav class="mt-6 min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain pb-5 [scrollbar-width:thin]" aria-label="Navegação móvel"><section v-for="section in sections" :key="section.label"><h2 class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ section.label }}</h2><Link v-for="item in section.items" :key="item.route" :href="route(item.route)" class="mb-1 flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium" :class="isActive(item.route) ? 'bg-white text-slate-950' : 'text-slate-300 hover:bg-white/10'" @click="closeMobileNavigation(false)"><component :is="item.icon" :size="20" aria-hidden="true" />{{ item.label }}</Link></section></nav>
            <div class="shrink-0 space-y-2 border-t border-white/10 pt-4"><Link :href="route('profile.edit')" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm text-slate-300 hover:bg-white/10" @click="closeMobileNavigation(false)"><span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-slate-800 text-emerald-300"><img v-if="avatar && !avatarFailed" :src="avatar" alt="" class="h-full w-full object-cover" @error="avatarFailed = true" /><span v-else>{{ initials }}</span></span>Meu perfil</Link><button type="button" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm text-rose-300 hover:bg-rose-400/10" @click="logout"><LogOut :size="19" />Sair</button></div>
        </aside></div>
        <QuickActionModal :show="quickActionOpen" @close="quickActionOpen = false" />
        <TsukiOnboarding v-if="$page.props.onboarding" :essential-steps="$page.props.onboarding.essentialSteps" :recommended-steps="$page.props.onboarding.recommendedSteps" :progress="$page.props.onboarding.progress" :initially-open="$page.props.onboarding.initiallyOpen" />
        <ToastHost />
    </div>
</template>
