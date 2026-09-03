<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GoogleLogo from '@/Components/GoogleLogo.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShieldCheck, Unlink } from '@lucide/vue';

defineProps({
    googleAuthenticationEnabled: { type: Boolean },
    googleIdentity: { type: Object, default: null },
});
</script>

<template>
    <Head title="Meu perfil" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Meu perfil
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdateProfileInformationForm class="max-w-xl" />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-xl" />
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
                                <span class="absolute left-3 grid h-9 w-9 place-items-center rounded-lg transition group-hover:bg-white">
                                    <GoogleLogo />
                                </span>
                                Continuar com Google
                            </a>
                            <p v-else class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Configure as credenciais do Google para habilitar o vínculo.</p>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
