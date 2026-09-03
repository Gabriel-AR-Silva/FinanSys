<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GoogleLogo from '@/Components/GoogleLogo.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, Eye, EyeOff, ShieldCheck, Sparkles, TrendingUp, WalletCards } from '@lucide/vue';
import { ref } from 'vue';

defineProps({
    canResetPassword: { type: Boolean },
    googleAuthenticationEnabled: { type: Boolean },
    googleError: { type: String, default: null },
    status: { type: String },
});

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);

const floatingMoney = [
    { symbol: 'R$', className: 'left-[8%] top-[14%] delay-1' },
    { symbol: '+', className: 'left-[18%] top-[72%] delay-3' },
    { symbol: '$', className: 'left-[42%] top-[8%] delay-2' },
    { symbol: 'R$', className: 'right-[12%] top-[18%] delay-4' },
    { symbol: '+', className: 'right-[28%] top-[76%] delay-2' },
    { symbol: '$', className: 'right-[6%] top-[62%] delay-1' },
];

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-slate-950 text-white">
        <Head title="Entrar" />

        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-emerald-400/15 blur-3xl"></div>
            <div class="absolute -bottom-40 right-0 h-[30rem] w-[30rem] rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="money-grid absolute inset-0 opacity-30"></div>
            <span
                v-for="item in floatingMoney"
                :key="`${item.symbol}-${item.className}`"
                class="money-float absolute grid h-12 w-12 place-items-center rounded-2xl border border-emerald-300/15 bg-white/5 font-mono text-sm font-bold text-emerald-200/50 shadow-2xl backdrop-blur-sm"
                :class="item.className"
            >{{ item.symbol }}</span>
        </div>

        <main class="relative z-10 mx-auto grid min-h-screen max-w-7xl items-center gap-10 px-5 py-10 lg:grid-cols-[1.05fr_0.95fr] lg:px-12">
            <section class="hidden max-w-xl lg:block">
                <div class="mb-10 flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-400/25">
                        <WalletCards :size="23" stroke-width="2.4" />
                    </div>
                    <span class="text-xl font-semibold tracking-tight">FinanSys</span>
                </div>

                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200">
                    <Sparkles :size="14" />
                    Clareza para suas escolhas
                </div>

                <h1 class="mt-6 text-5xl font-semibold leading-[1.08] tracking-tight xl:text-6xl">
                    Seu dinheiro,
                    <span class="text-emerald-400">sem mistério.</span>
                </h1>
                <p class="mt-6 max-w-lg text-lg leading-8 text-slate-300">
                    Contas, caixinhas e lançamentos reunidos em uma visão simples para você decidir melhor todos os dias.
                </p>

                <div class="mt-10 grid max-w-lg grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-md">
                        <TrendingUp class="text-emerald-400" :size="22" />
                        <p class="mt-4 text-sm font-semibold">Visão consolidada</p>
                        <p class="mt-1 text-xs leading-5 text-slate-400">Entenda seu saldo e sua evolução em poucos segundos.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-md">
                        <ShieldCheck class="text-cyan-300" :size="22" />
                        <p class="mt-4 text-sm font-semibold">Espaço protegido</p>
                        <p class="mt-1 text-xs leading-5 text-slate-400">Seus dados ficam isolados dentro da sua conta.</p>
                    </div>
                </div>
            </section>

            <section class="mx-auto w-full max-w-md">
                <div class="mb-8 flex items-center justify-center gap-3 lg:hidden">
                    <div class="grid h-10 w-10 place-items-center rounded-2xl bg-emerald-400 text-slate-950">
                        <WalletCards :size="21" />
                    </div>
                    <span class="text-xl font-semibold">FinanSys</span>
                </div>

                <div class="rounded-[2rem] border border-white/10 bg-white/[0.97] p-6 text-slate-900 shadow-2xl shadow-black/30 sm:p-8">
                    <p class="text-sm font-semibold text-emerald-700">Bem-vindo de volta</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight">Acesse seu painel</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Entre para continuar cuidando da sua vida financeira.</p>

                    <div v-if="status" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                        {{ status }}
                    </div>

                    <div v-if="googleError" class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">
                        {{ googleError }}
                    </div>

                    <form class="mt-7 flex flex-col gap-5" @submit.prevent="submit">
                        <div class="flex flex-col gap-2">
                            <label for="email" class="text-sm font-semibold text-slate-700">E-mail</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="voce@exemplo.com"
                                class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 px-4 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-4">
                                <label for="password" class="text-sm font-semibold text-slate-700">Senha</label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-xs font-semibold text-emerald-700 transition hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                >Esqueci minha senha</Link>
                            </div>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Digite sua senha"
                                    class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 px-4 pr-12 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-500 focus:bg-white focus:ring-4 focus:ring-emerald-500/10"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-xl text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-500"
                                    :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" :size="19" />
                                    <Eye v-else :size="19" />
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                            <Checkbox v-model:checked="form.remember" name="remember" />
                            Manter conectado neste dispositivo
                        </label>

                        <button
                            type="submit"
                            class="group flex h-12 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white shadow-lg shadow-slate-950/15 transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-emerald-600/20 focus:outline-none focus:ring-4 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            <span>{{ form.processing ? 'Entrando...' : 'Entrar no FinanSys' }}</span>
                            <ArrowRight v-if="!form.processing" :size="18" class="transition-transform group-hover:translate-x-1" />
                            <span v-else class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
                        </button>
                    </form>

                    <div v-if="googleAuthenticationEnabled" class="mt-5 flex flex-col gap-4">
                        <div class="flex items-center gap-3 text-xs font-medium uppercase tracking-[0.16em] text-slate-400">
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <span>ou</span>
                            <span class="h-px flex-1 bg-slate-200"></span>
                        </div>
                        <a :href="route('google.login')" class="group relative flex h-12 w-full items-center justify-center rounded-xl border border-[#747775] bg-white px-12 text-sm font-medium leading-5 text-[#1f1f1f] shadow-sm transition hover:bg-[#f8fafd] hover:shadow-md focus:outline-none focus:ring-4 focus:ring-blue-500/20 active:bg-[#f1f3f4]">
                            <span class="absolute left-3 grid h-9 w-9 place-items-center rounded-lg transition group-hover:bg-white">
                                <GoogleLogo />
                            </span>
                            Entrar com Google
                        </a>
                    </div>

                    <p class="mt-6 flex items-center justify-center gap-2 text-center text-xs text-slate-400">
                        <ShieldCheck :size="14" />
                        Ambiente pessoal com acesso protegido
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.money-grid {
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: linear-gradient(to bottom, black, transparent 90%);
}

.money-float { animation: money-float 7s ease-in-out infinite; }
.delay-1 { animation-delay: -1s; }
.delay-2 { animation-delay: -2.5s; }
.delay-3 { animation-delay: -4s; }
.delay-4 { animation-delay: -5.5s; }

@keyframes money-float {
    0%, 100% { transform: translate3d(0, 0, 0) rotate(-5deg); }
    50% { transform: translate3d(0, -18px, 0) rotate(6deg); }
}

@media (prefers-reduced-motion: reduce) {
    .money-float { animation: none; }
}
</style>
