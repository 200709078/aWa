<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{ title: string }>();

const menuOpen = ref(false);

const user = computed(() => {
    const props = usePage().props as unknown as { auth: { user: { name: string } | null } };
    return props.auth.user;
});

const currentUrl = computed(() => usePage().url);

const nav = [
    { label: 'Panel', href: '/' },
    { label: 'Akademik Yıllar', href: '/academic-years' },
    { label: 'Sınav Haftaları', href: '/exam-weeks' },
    { label: 'Salonlar', href: '/rooms' },
    { label: 'Şubeler', href: '/branches' },
    { label: 'Öğrenciler', href: '/students' },
    { label: 'Dağıtım', href: '/distribution' },
    { label: 'Çıktılar', href: '/reports' },
    { label: 'Ayarlar', href: '/settings' },
];

const flashSuccess = computed(() => {
    const props = usePage().props as unknown as { flash: { success: string | null } };
    return props.flash.success;
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
        <header class="bg-white shadow-sm">
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
                        <span class="font-semibold text-gray-900">Kelebek</span>
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-gray-600 sm:inline">{{ user?.name }}</span>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-100"
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
            <aside class="hidden w-56 shrink-0 md:block">
                <nav class="space-y-1 rounded-lg bg-white p-3 shadow-sm">
                    <Link v-for="item in nav" :key="item.href" :href="item.href" :class="linkClass(item.href)">
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <main class="min-w-0 flex-1">
                <p v-if="flashSuccess" class="mb-4 rounded-md bg-green-50 px-4 py-2 text-sm text-green-800">
                    {{ flashSuccess }}
                </p>
                <slot />
            </main>
        </div>
    </div>
</template>
