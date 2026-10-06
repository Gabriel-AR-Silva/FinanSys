<script setup>
import { LockKeyhole, Target } from '@lucide/vue';
</script>

<template>
    <section class="mt-5" aria-labelledby="goals-heading">
        <article class="overflow-hidden rounded-2xl border border-violet-200 bg-gradient-to-br from-white via-violet-50/60 to-slate-50 shadow-sm">
            <div class="flex flex-col gap-5 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-violet-100 text-violet-700">
                        <Target :size="23" />
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="goals-heading" class="text-xl font-semibold text-slate-950">Metas</h2>
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-950 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">
                                <LockKeyhole :size="11" /> Pausado
                            </span>
                        </div>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            O módulo de metas está temporariamente bloqueado enquanto a estrutura financeira principal da V2 é consolidada. Nenhuma meta existente foi apagada.
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-violet-200 bg-white/80 px-4 py-3 text-sm text-slate-600 lg:max-w-sm">
                    <p class="font-semibold text-slate-900">Por que está bloqueado?</p>
                    <p class="mt-1 leading-5">Antes de definir objetivos, o FinanSys precisa refletir com confiança seu caixa, compromissos e rotina real. O módulo volta quando essa base estiver estável.</p>
                </div>
            </div>
        </article>
    </section>
</template>
