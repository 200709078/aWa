<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineProps<{ title: string }>();

const menuOpen = ref(false);

const user = computed(() => {
    const props = usePage().props as unknown as { auth: { user: { name: string } | null } };
    return props.auth.user;
});

const currentUrl = computed(() => usePage().url);

const nav = [
    { label: 'Giriş', href: '/' },
    { label: 'Akademik Yıllar', href: '/academic-years' },
    { label: 'Sınav Haftaları', href: '/exam-weeks' },
    { label: 'Salonlar', href: '/rooms' },
    { label: 'Sınıflar', href: '/branches' },
    { label: 'Öğrenciler', href: '/students' },
    { label: 'Dağıtım', href: '/distribution' },
];

interface Toast {
    id: number;
    type: 'success' | 'error';
    title: string;
    messages: string[];
}

const toasts = ref<Toast[]>([]);
let toastId = 0;
const toastTimers = new Map<number, ReturnType<typeof setTimeout>>();

function pushToast(type: Toast['type'], title: string, messages: string[]) {
    if (messages.length === 0) return;
    const id = ++toastId;
    toasts.value.push({ id, type, title, messages });
    toastTimers.set(
        id,
        setTimeout(() => dismissToast(id), 6000),
    );
}

function dismissToast(id: number) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
    const timer = toastTimers.get(id);
    if (timer) {
        clearTimeout(timer);
        toastTimers.delete(id);
    }
}

function collectToasts(props: unknown) {
    const { flash, errors } = props as {
        flash?: { success?: string | null; error?: string | null };
        errors?: Record<string, string>;
    };
    if (flash?.success) pushToast('success', '', [flash.success]);
    if (flash?.error) pushToast('error', '', [flash.error]);
    const messages = Object.values(errors ?? {});
    if (messages.length > 0) pushToast('error', 'Hata', messages);
}

onMounted(() => {
    collectToasts(usePage().props);
});

const offRouterSuccess = router.on('success', (event) => {
    const page = (event as unknown as { detail: { page: { props: unknown } } }).detail.page;
    collectToasts(page.props);
});

onUnmounted(() => {
    offRouterSuccess();
});

function isActive(href: string): boolean {
    return href === '/' ? currentUrl.value === '/' : currentUrl.value.startsWith(href);
}

function linkClass(href: string): string {
    const base = 'block rounded-md px-3 py-2 text-sm font-medium';
    return isActive(href)
        ? `${base} bg-indigo-100 text-indigo-800`
        : `${base} text-gray-700 hover:bg-gray-100`;
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-gray-50">
        <header class="sticky top-0 z-30 bg-white shadow-sm print:hidden">
            <div class="flex items-center justify-between px-2 py-3">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-md p-2 text-gray-600 hover:bg-gray-100 md:hidden"
                        @click="menuOpen = !menuOpen"
                    >
                        Menü
                    </button>
                    <Link href="/" class="flex items-center gap-2">
                        <img :src="'/favicon.png'" alt="Kelebek logosu" class="h-8 w-8 object-contain" />
                        <span class="font-semibold text-gray-900">Kelebek Oturma Planı</span>
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-gray-600 sm:inline">{{ user?.name }}</span>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
                        @click="logout"
                    >
                        Çıkış
                    </button>
                </div>
            </div>
            <nav v-if="menuOpen" class="space-y-1 border-t px-4 py-3 md:hidden">
                <Link v-for="item in nav" :key="item.href" :href="item.href" :class="linkClass(item.href)">
                    {{ item.label }}
                </Link>
            </nav>
        </header>

        <div class="flex gap-6 px-2 py-6">
            <aside class="sticky top-20 z-20 hidden w-56 shrink-0 self-start md:block print:hidden">
                <nav class="space-y-1 rounded-lg bg-white p-3 shadow-sm">
                    <Link v-for="item in nav" :key="item.href" :href="item.href" :class="linkClass(item.href)">
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <main class="min-w-0 flex-1">
                <slot />
            </main>
        </div>
        <div class="fixed bottom-4 right-4 z-50 flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-3" aria-live="polite">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                role="status"
                class="flex animate-[kelebek-toast-in_250ms_ease] items-start gap-3 rounded-lg border-l-4 bg-white p-4 shadow-xl"
                :class="toast.type === 'success' ? 'border-green-600' : 'border-red-600'"
            >
                <svg
                    v-if="toast.type === 'success'"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    aria-hidden="true"
                    class="h-5 w-5 shrink-0 text-green-600"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    aria-hidden="true"
                    class="h-5 w-5 shrink-0 text-red-600"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="min-w-0 flex-1 text-sm leading-5 text-gray-900">
                    <div v-if="toast.title" class="font-semibold">{{ toast.title }}</div>
                    <ul v-if="toast.messages.length > 1" class="list-disc ps-4">
                        <li v-for="(message, index) in toast.messages" :key="index">{{ message }}</li>
                    </ul>
                    <div v-else>{{ toast.messages[0] }}</div>
                </div>
                <button
                    type="button"
                    title="Kapat"
                    aria-label="Kapat"
                    class="shrink-0 p-0.5 leading-none text-gray-400 hover:text-red-600"
                    @click="dismissToast(toast.id)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
        <div
            class="pointer-events-none fixed bottom-2 right-3 z-50 select-none text-[20px] font-bold tracking-wide text-black"
        >
            made by <span class="font-bold text-black">m</span><span class="font-bold text-blue-900">ADEM</span><span class="font-bold text-black">atik</span>
        </div>
    </div>
</template>

<style>
@keyframes kelebek-toast-in {
    from {
        opacity: 0;
        transform: translateY(0.75rem);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
