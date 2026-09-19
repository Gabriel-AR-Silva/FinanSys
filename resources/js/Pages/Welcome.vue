<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ChartNoAxesCombined, Mail, ShieldCheck, WalletCards } from '@lucide/vue';

defineProps({
    canLogin: { type: Boolean },
});
</script>

<template>
    <div class="flex min-h-screen flex-col overflow-hidden bg-slate-950 text-white">
        <Head title="Finanças pessoais com clareza" />

        <header class="relative z-10 border-b border-white/10">
            <div class="mx-auto flex h-20 w-full max-w-7xl items-center justify-between gap-4 px-5 sm:px-8 lg:px-12">
                <Link :href="route('home')" class="flex items-center gap-3" aria-label="FinanSys — página inicial">
                    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-400/20">
                        <WalletCards :size="23" :stroke-width="2.4" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight">FinanSys</span>
                </Link>

                <Link
                    v-if="canLogin"
                    :href="$page.props.auth.user ? route('dashboard') : route('login')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 text-sm font-semibold text-white transition hover:border-emerald-300/40 hover:bg-white/15 focus:outline-none focus:ring-4 focus:ring-emerald-400/20"
                >
                    Área administrativa
                    <ArrowRight :size="17" />
                </Link>
            </div>
        </header>

        <main class="relative isolate flex flex-1 items-center">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="absolute -left-40 top-0 h-[30rem] w-[30rem] rounded-full bg-emerald-400/15 blur-3xl"></div>
                <div class="absolute -right-48 bottom-0 h-[34rem] w-[34rem] rounded-full bg-cyan-400/10 blur-3xl"></div>
                <div class="landing-grid absolute inset-0 opacity-30"></div>
            </div>

            <section class="mx-auto grid w-full max-w-7xl items-center gap-12 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[1.05fr_0.95fr] lg:px-12 lg:py-28">
                <div class="max-w-3xl">
                    <p class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-200">
                        <ShieldCheck :size="15" />
                        Controle pessoal e protegido
                    </p>
                    <h1 class="mt-6 text-4xl font-semibold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                        Sua vida financeira,
                        <span class="text-emerald-400">visível de verdade.</span>
                    </h1>
                    <p class="mt-6 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                        Organize contas, caixinhas e movimentações em um só lugar. O FinanSys transforma seus lançamentos em uma visão clara para decisões mais conscientes.
                    </p>

                    <Link
                        v-if="canLogin"
                        :href="$page.props.auth.user ? route('dashboard') : route('login')"
                        class="mt-8 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-emerald-400 px-5 text-sm font-semibold text-slate-950 shadow-xl shadow-emerald-500/15 transition hover:-translate-y-0.5 hover:bg-emerald-300 focus:outline-none focus:ring-4 focus:ring-emerald-400/25"
                    >
                        {{ $page.props.auth.user ? 'Abrir meu painel' : 'Acessar área administrativa' }}
                        <ArrowRight :size="18" />
                    </Link>
                </div>

                <div class="mx-auto w-full max-w-lg lg:mx-0 lg:justify-self-end" aria-hidden="true">
                    <div class="rounded-[2rem] border border-white/10 bg-white/[0.07] p-5 shadow-2xl shadow-black/30 backdrop-blur sm:p-7">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Visão consolidada</p>
                                <p class="mt-2 text-2xl font-semibold">Tudo no mesmo ritmo</p>
                            </div>
                            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-400/15 text-emerald-300">
                                <ChartNoAxesCombined :size="23" />
                            </span>
                        </div>
                        <div class="mt-8 flex h-44 items-end gap-3 rounded-2xl border border-white/10 bg-slate-950/50 p-5">
                            <span v-for="height in [35, 52, 44, 70, 61, 84, 76]" :key="height" class="flex-1 rounded-t-lg bg-gradient-to-t from-emerald-500/40 to-emerald-300" :style="{ height: `${height}%` }"></span>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-white/10 bg-white/5 p-4"><p class="text-xs text-slate-400">Organização</p><p class="mt-1 font-semibold text-emerald-300">Contas e caixinhas</p></div>
                            <div class="rounded-xl border border-white/10 bg-white/5 p-4"><p class="text-xs text-slate-400">Acompanhamento</p><p class="mt-1 font-semibold text-cyan-200">Evolução financeira</p></div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="relative z-10 border-t border-white/10">
            <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-5 py-7 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
                <p>© {{ new Date().getFullYear() }} FinanSys. Um projeto Garssoft.</p>
                <a href="https://garssoft.site" target="_blank" rel="noreferrer" class="inline-flex min-h-10 items-center gap-2 self-start rounded-lg px-2 text-slate-300 transition hover:text-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-400 sm:self-auto">
                    <Mail :size="17" />
                    Contato Garssoft
                </a>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.landing-grid {
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
    background-size: 64px 64px;
    mask-image: linear-gradient(to bottom, black, transparent 92%);
}
</style>
