<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

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

const flashSuccess = computed(() => {
    const props = usePage().props as unknown as { flash: { success: string | null } };
    return props.flash.success;
});

const toastMessage = ref('');
const toastVisible = ref(false);
let toastTimer: ReturnType<typeof setTimeout> | null = null;

function showToast(message: string) {
    toastMessage.value = message;
    toastVisible.value = true;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toastVisible.value = false), 3000);
}

watch(flashSuccess, (value) => {
    if (value) showToast(value);
});

onMounted(() => {
    if (flashSuccess.value) showToast(flashSuccess.value);
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
        <div
            v-if="toastVisible"
            class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-md bg-gray-900 px-4 py-2 text-sm text-white shadow-lg"
        >
            {{ toastMessage }}
        </div>
        <div
            class="pointer-events-none fixed bottom-2 right-3 z-50 select-none text-[20px] font-bold tracking-wide text-black"
        >
            made by <span class="font-bold text-black">m</span><span class="font-bold text-blue-900">ADEM</span><span class="font-bold text-black">atik</span>
        </div>
    </div>
</template>
