<script setup>
import { Nfc } from '@lucide/vue';
</script>

<template>
    <div class="min-h-screen bg-[var(--fs-surface-subtle)] text-[var(--fs-text)]">
        <aside class="fixed inset-y-0 left-0 hidden w-56 flex-col border-r border-[var(--fs-border-soft)] bg-[var(--fs-surface)] px-4 py-6 lg:flex" aria-label="Navegação do acesso NFC">
            <div class="mb-8 px-2 text-sm font-bold tracking-tight">FinanSys · NFC</div>
            <div class="flex items-center gap-3 rounded-xl bg-[var(--fs-info-soft)] px-3 py-3 text-sm font-semibold text-[var(--fs-info)]" aria-current="page">
                <Nfc :size="19" aria-hidden="true" /> Plaquinhas NFC
            </div>
        </aside>
        <main class="min-w-0 px-4 py-7 sm:px-6 sm:py-9 lg:pl-[16rem] lg:pr-10">
            <div class="mx-auto min-w-0 max-w-7xl"><slot /></div>
        </main>
    </div>
</template>
