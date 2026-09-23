<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GoogleLogo from '@/Components/GoogleLogo.vue';
import OperationalDataResetModal from '@/Components/OperationalDataResetModal.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Download, KeyRound, Settings, ShieldCheck, Trash2, Unlink, UserRound } from '@lucide/vue';
import { nextTick, onMounted, ref, watch } from 'vue';

defineProps({
    googleAuthenticationEnabled: { type: Boolean },
    googleIdentity: { type: Object, default: null },
});

const page = usePage();
const storageKey = 'finansys.profile.active-tab';
const tabs = [
    { id: 'profile', label: 'Perfil', icon: UserRound },
    { id: 'password', label: 'Senha', icon: KeyRound },
    { id: 'google', label: 'Google', icon: ShieldCheck },
    { id: 'advanced', label: 'Avançado', icon: Settings },
];
const activeTab = ref('profile');
const resetModalOpen = ref(false);

const selectTab = (id) => {
    if (!tabs.some((tab) => tab.id === id)) return;
    activeTab.value = id;
    try { window.sessionStorage.setItem(storageKey, id); } catch (_) { /* armazenamento opcional */ }
};

const moveTab = async (direction) => {
    const index = tabs.findIndex((tab) => tab.id === activeTab.value);
    const next = tabs[(index + direction + tabs.length) % tabs.length].id;
    selectTab(next);
    await nextTick();
    document.getElementById(`profile-tab-${next}`)?.focus();
};

const selectErrorTab = (errors = {}) => {
    if (['current_password', 'password', 'password_confirmation'].some((name) => errors[name])) selectTab('password');
    else if (['avatar', 'name', 'email'].some((name) => errors[name])) selectTab('profile');
};

onMounted(() => {
    try {
        const saved = window.sessionStorage.getItem(storageKey);
        if (saved) selectTab(saved);
    } catch (_) { /* armazenamento opcional */ }
    selectErrorTab(page.props.errors);
});
watch(() => page.props.errors, selectErrorTab);
</script>

<template>
    <Head title="Meu perfil" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl">
            <header class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-950">Meu perfil</h1>
                <p class="mt-1 text-sm text-slate-600">Gerencie seus dados, acessos e configurações.</p>
            </header>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-3 pt-3 sm:px-6">
                    <div class="grid min-w-0 grid-cols-2 gap-x-2 sm:grid-cols-4" role="tablist" aria-label="Seções do perfil">
                        <button
                            v-for="tab in tabs"
                            :id="`profile-tab-${tab.id}`"
                            :key="tab.id"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === tab.id"
                            :aria-controls="`profile-panel-${tab.id}`"
                            :tabindex="activeTab === tab.id ? 0 : -1"
                            class="inline-flex min-h-11 min-w-0 items-center justify-center gap-2 border-b-2 px-2 py-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-inset"
                            :class="activeTab === tab.id ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-slate-500 hover:border-slate-200 hover:text-slate-900'"
                            @click="selectTab(tab.id)"
                            @keydown.left.prevent="moveTab(-1)"
                            @keydown.right.prevent="moveTab(1)"
                            @keydown.home.prevent="selectTab(tabs[0].id)"
                            @keydown.end.prevent="selectTab(tabs[tabs.length - 1].id)"
                        ><component :is="tab.icon" :size="17" class="shrink-0" aria-hidden="true" />{{ tab.label }}</button>
                    </div>
                </div>

                <section id="profile-panel-profile" v-show="activeTab === 'profile'" role="tabpanel" aria-labelledby="profile-tab-profile" tabindex="0" class="p-4 sm:p-8">
                    <UpdateProfileInformationForm class="max-w-2xl" />
                </section>
                <section id="profile-panel-password" v-show="activeTab === 'password'" role="tabpanel" aria-labelledby="profile-tab-password" tabindex="0" class="p-4 sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </section>
                <section id="profile-panel-google" v-show="activeTab === 'google'" role="tabpanel" aria-labelledby="profile-tab-google" tabindex="0" class="p-4 sm:p-8">
                    <div class="max-w-xl">
                        <header class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><ShieldCheck :size="20" /></span>
                            <div><h2 class="text-lg font-medium text-gray-900">Login com Google</h2><p class="mt-1 text-sm text-gray-600">Vincule somente a sua conta autorizada. O Google não cria novos usuários no FinanSys.</p></div>
                        </header>
                        <div v-if="googleIdentity" class="mt-6 flex flex-col gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0"><p class="text-sm font-semibold text-emerald-900">Conta vinculada</p><p class="mt-1 truncate text-sm text-emerald-800">{{ googleIdentity.email }}</p></div>
                            <Link :href="route('google.unlink')" method="delete" as="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50"><Unlink :size="16" />Desvincular</Link>
                        </div>
                        <div v-else class="mt-6">
                            <a v-if="googleAuthenticationEnabled" :href="route('google.link')" class="group relative flex h-12 w-full max-w-sm items-center justify-center rounded-xl border border-[#747775] bg-white px-12 text-sm font-medium leading-5 text-[#1f1f1f] shadow-sm transition hover:bg-[#f8fafd] hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-500/20 active:bg-[#f1f3f4]">
                                <span class="absolute left-3 grid h-9 w-9 place-items-center rounded-lg transition group-hover:bg-white"><GoogleLogo /></span>Continuar com Google
                            </a>
                            <p v-else class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Configure as credenciais do Google para habilitar o vínculo.</p>
                        </div>
                    </div>
                </section>
                <section id="profile-panel-advanced" v-show="activeTab === 'advanced'" role="tabpanel" aria-labelledby="profile-tab-advanced" tabindex="0" class="p-4 sm:p-8">
                    <div class="max-w-xl">
                        <header class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-700"><Trash2 :size="20" /></span>
                            <div><h2 class="text-lg font-medium text-gray-900">Configurações avançadas</h2><p class="mt-1 text-sm leading-6 text-gray-600">Limpe somente dados gerados durante uso e testes. Categorias, contas, caixinhas, cartões e configurações básicas permanecem.</p></div>
                        </header>
                        <div class="mt-6 rounded-2xl border border-sky-200 bg-sky-50/60 p-4">
                            <h3 class="text-sm font-semibold text-sky-950">Exportação financeira para diagnóstico</h3>
                            <p class="mt-1 text-sm leading-6 text-sky-900/80">Baixa um JSON com seus dados financeiros e os indicadores calculados pelo FinanSys. Ele serve para recalcular números externamente e comparar divergências. Senha, tokens e credenciais do Google não são exportados.</p>
                            <a :href="route('financial-diagnostic-export.show')" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-600"><Download :size="17" />Baixar JSON de diagnóstico</a>
                        </div>
                        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50/60 p-4">
                            <h3 class="text-sm font-semibold text-rose-950">Limpar dados de uso</h3>
                            <p class="mt-1 text-sm leading-6 text-rose-900/80">Remove movimentações, operações de cartão, previsões, importações OFX e dados derivados. Use para voltar a um estado limpo de teste sem reconstruir sua configuração.</p>
                            <button type="button" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-600" @click="resetModalOpen = true"><Trash2 :size="17" />Preparar limpeza</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <OperationalDataResetModal :show="resetModalOpen" @close="resetModalOpen = false" />
    </AuthenticatedLayout>
</template>
