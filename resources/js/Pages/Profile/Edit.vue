<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GoogleLogo from '@/Components/GoogleLogo.vue';
import OperationalDataResetModal from '@/Components/OperationalDataResetModal.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MonitorSmartphone, ShieldCheck, Trash2, Unlink } from '@lucide/vue';
import { ref } from 'vue';

defineProps({
    googleAuthenticationEnabled: { type: Boolean },
    googleIdentity: { type: Object, default: null },
    trustedDevices: { type: Array, default: () => [] },
});

const resetModalOpen = ref(false);
const selectedDevice = ref(null);
const revokeForm = useForm({ password: '' });

const revokeDevice = (device) => {
    selectedDevice.value = device.id;
    revokeForm.delete(route('profile.trusted-devices.destroy', device.id), {
        preserveScroll: true,
        onSuccess: () => revokeForm.reset(),
        onFinish: () => { selectedDevice.value = null; },
    });
};
</script>

<template>
    <Head title="Meu perfil" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Meu perfil</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <UpdateProfileInformationForm class="max-w-xl" />
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <section class="max-w-xl">
                        <header class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700"><MonitorSmartphone :size="20" /></span>
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">Dispositivos confiáveis</h2>
                                <p class="mt-1 text-sm leading-6 text-gray-600">No máximo dois aparelhos podem acessar sua conta. Um terceiro aparelho é bloqueado mesmo com e-mail e senha corretos.</p>
                            </div>
                        </header>

                        <div v-if="trustedDevices.length" class="mt-6 space-y-3">
                            <article v-for="device in trustedDevices" :key="device.id" class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-slate-900">{{ device.name }}</p>
                                            <span v-if="device.current" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Este aparelho</span>
                                        </div>
                                        <p class="mt-1 break-all text-xs text-slate-500">Último IP: {{ device.last_ip_address || 'não identificado' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Último uso: {{ new Date(device.last_used_at).toLocaleString('pt-BR') }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="min-h-11 shrink-0 rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:opacity-50"
                                        :disabled="!revokeForm.password || revokeForm.processing"
                                        @click="revokeDevice(device)"
                                    >
                                        {{ selectedDevice === device.id && revokeForm.processing ? 'Removendo...' : 'Remover' }}
                                    </button>
                                </div>
                            </article>
                        </div>
                        <p v-else class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Nenhum aparelho foi registrado ainda. O próximo login válido registrará o primeiro.</p>

                        <div class="mt-5">
                            <label for="device-password" class="text-sm font-semibold text-slate-700">Senha atual para remover um aparelho</label>
                            <input id="device-password" v-model="revokeForm.password" type="password" autocomplete="current-password" class="mt-2 h-12 w-full rounded-xl border-slate-300" />
                            <p v-if="revokeForm.errors.password" class="mt-1 text-xs text-rose-600">{{ revokeForm.errors.password }}</p>
                        </div>
                    </section>
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <section class="max-w-xl">
                        <header class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><ShieldCheck :size="20" /></span>
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">Login com Google</h2>
                                <p class="mt-1 text-sm text-gray-600">Vincule somente a sua conta autorizada. O Google não cria novos usuários no FinanSys.</p>
                            </div>
                        </header>

                        <div v-if="googleIdentity" class="mt-6 flex flex-col gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-emerald-900">Conta vinculada</p>
                                <p class="mt-1 truncate text-sm text-emerald-800">{{ googleIdentity.email }}</p>
                            </div>
                            <Link :href="route('google.unlink')" method="delete" as="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                <Unlink :size="16" />Desvincular
                            </Link>
                        </div>

                        <div v-else class="mt-6">
                            <a v-if="googleAuthenticationEnabled" :href="route('google.link')" class="group relative flex h-12 w-full max-w-sm items-center justify-center rounded-xl border border-[#747775] bg-white px-12 text-sm font-medium leading-5 text-[#1f1f1f] shadow-sm transition hover:bg-[#f8fafd] hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-500/20 active:bg-[#f1f3f4]">
                                <span class="absolute left-3 grid h-9 w-9 place-items-center rounded-lg transition group-hover:bg-white"><GoogleLogo /></span>
                                Continuar com Google
                            </a>
                            <p v-else class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Configure as credenciais do Google para habilitar o vínculo.</p>
                        </div>
                    </section>
                </div>

                <div class="border border-rose-200 bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <section class="max-w-xl">
                        <header class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-700"><Trash2 :size="20" /></span>
                            <div>
                                <h2 class="text-lg font-medium text-gray-900">Configurações avançadas</h2>
                                <p class="mt-1 text-sm leading-6 text-gray-600">Limpe somente dados gerados durante uso e testes. Categorias, contas, caixinhas, cartões e configurações básicas permanecem.</p>
                            </div>
                        </header>

                        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50/60 p-4">
                            <h3 class="text-sm font-semibold text-rose-950">Limpar dados de uso</h3>
                            <p class="mt-1 text-sm leading-6 text-rose-900/80">Remove movimentações, operações de cartão, previsões, importações OFX e dados derivados. Use para voltar a um estado limpo de teste sem reconstruir sua configuração.</p>
                            <button type="button" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-600" @click="resetModalOpen = true">
                                <Trash2 :size="17" />Preparar limpeza
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <OperationalDataResetModal :show="resetModalOpen" @close="resetModalOpen = false" />
    </AuthenticatedLayout>
</template>
