<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0">
        <div>
            <Link href="/" aria-label="FinanSys — página inicial" class="inline-flex items-center gap-3 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                <img src="/finansys-icon.svg" alt="" width="72" height="72" class="h-16 w-16 shrink-0 rounded-2xl" />
                <span class="text-xl font-bold tracking-tight text-slate-950">FinanSys</span>
            </Link>
        </div>
        <div class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"><slot /></div>
    </div>
</template>
