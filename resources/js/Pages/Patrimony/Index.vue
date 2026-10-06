<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Landmark, LockKeyhole } from '@lucide/vue';
</script>

<template>
    <Head title="Patrimônio" />
    <AuthenticatedLayout>
        <section class="mx-auto max-w-5xl">
            <article class="overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-white via-emerald-50/60 to-slate-50 shadow-sm">
                <div class="flex flex-col gap-5 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                            <Landmark :size="23" />
                        </span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Patrimônio</h1>
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-950 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">
                                    <LockKeyhole :size="11" /> Pausado
                                </span>
                            </div>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                                O módulo patrimonial está temporariamente bloqueado enquanto o modelo de ativos, dívidas e avaliação é redesenhado. Os registros existentes permanecem preservados.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-emerald-200 bg-white/80 px-4 py-3 text-sm text-slate-600 lg:max-w-sm">
                        <p class="font-semibold text-slate-900">Próxima versão</p>
                        <p class="mt-1 leading-5">A estrutura futura vai separar tipos de ativos e passivos e permitir regras específicas para veículos, imóveis e bens pessoais sem forçar tudo no mesmo formulário.</p>
                    </div>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
