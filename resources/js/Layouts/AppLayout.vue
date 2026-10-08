<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import NavIcon from '../Components/NavIcon.vue';

defineProps<{ title: string }>();

const menuOpen = ref(false);
const schoolMenuOpen = ref(false);

interface PageUser {
    name: string;
    role: string;
}

interface PageSchool {
    id: number;
    name: string;
    kurum_kodu: string;
}

const user = computed(() => {
    const props = usePage().props as unknown as { auth: { user: PageUser | null } };
    return props.auth.user;
});

const currentSchool = computed(() => {
    const props = usePage().props as unknown as { current_school: PageSchool | null };
    return props.current_school ?? null;
});

const mySchools = computed(() => {
    const props = usePage().props as unknown as { my_schools: PageSchool[] };
    return props.my_schools ?? [];
});

const isSuperAdmin = computed(() => user.value?.role === 'super_admin');

const currentUrl = computed(() => usePage().url);

interface NavLink {
    label: string;
    href: string;
    icon: string;
    disabled?: boolean;
}

interface NavGroup {
    label: string;
    href?: string;
    icon: string;
    danger?: boolean;
    children: NavLink[];
}

type NavEntry = NavLink | NavGroup;

function isGroup(entry: NavEntry): entry is NavGroup {
    return (entry as NavGroup).children !== undefined;
}

const nav = computed<NavEntry[]>(() => {
    const items: NavEntry[] = [
        { label: 'Giriş', href: '/', icon: 'home' },
        {
            label: 'Kurum',
            icon: 'folder',
            children: [
                { label: 'Akademik Yıllar', href: '/academic-years', icon: 'calendar' },
                { label: 'Sınıflar', href: '/branches', icon: 'users' },
                { label: 'Öğrenciler', href: '/students', icon: 'user' },
                { label: 'Rehber Aktarma', href: '/rehber-aktarma', icon: 'book' },
                { label: 'Mezunlar', href: '/mezunlar', icon: 'favicon' },
                { label: 'Personel', href: '/personel', icon: 'users' },
                { label: 'Arşiv', href: '/arsiv', icon: 'archive' },
            ],
        },
        {
            label: 'Rehberlik',
            icon: 'book',
            children: [
                { label: 'Bilgi Formları', href: '/bilgi-formlari', icon: 'user' },
                { label: 'Oturma Planları', href: '/oturme-planlari', icon: 'grid' },
            ],
        },
        {
            label: 'Kelebek',
            icon: 'logo',
            children: [
                { label: 'Salonlar', href: '/rooms', icon: 'grid' },
                { label: 'Sınav Haftaları', href: '/exam-weeks', icon: 'calendarDays' },
                { label: 'Dağıtım', href: '/distribution', icon: 'arrows' },
            ],
        },
    ];
    if (isSuperAdmin.value) {
        items.push({
            label: 'Yönetim',
            icon: 'shield',
            danger: true,
            children: [
                { label: 'Okullar', href: '/schools', icon: 'building' },
                { label: 'Kullanıcılar', href: '/users', icon: 'user' },
            ],
        });
    }
    return items;
});

function isActive(href: string): boolean {
    return href === '/' ? currentUrl.value === '/' : currentUrl.value.startsWith(href);
}

function isGroupActive(group: NavGroup): boolean {
    return (group.href ? isActive(group.href) : false) || group.children.some((child) => isActive(child.href));
}

const openGroups = ref<Record<string, boolean>>({});

for (const entry of nav.value) {
    if (isGroup(entry)) {
        openGroups.value[entry.label] = isGroupActive(entry);
    }
}

function toggleGroup(label: string) {
    openGroups.value[label] = !openGroups.value[label];
}

function switchSchool(id: number) {
    schoolMenuOpen.value = false;
    if (currentSchool.value?.id !== id) {
        router.post('/select-school', { school_id: id });
    }
}

interface Toast {
    id: number;
    type: 'success' | 'error';
    title: string;
    messages: string[];
}

const toasts = ref<Toast[]>([]);
let toastId = 0;
let lastToastKey = '';
let lastToastAt = 0;
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
    const items: { type: Toast['type']; title: string; messages: string[] }[] = [];
    if (flash?.success) items.push({ type: 'success', title: '', messages: [flash.success] });
    if (flash?.error) items.push({ type: 'error', title: '', messages: [flash.error] });
    const messages = Object.values(errors ?? {});
    if (messages.length > 0) items.push({ type: 'error', title: 'Hata', messages });

    for (const item of items) {
        const key = `${item.type}|${item.title}|${item.messages.join('\n')}`;
        const now = Date.now();
        // Aynı ziyaret mount + router success'i art arda tetikler; çift gösterimi engelle.
        if (key === lastToastKey && now - lastToastAt < 500) continue;
        lastToastKey = key;
        lastToastAt = now;
        pushToast(item.type, item.title, item.messages);
    }
}

onMounted(() => {
    collectToasts(usePage().props);
    window.addEventListener('awa-toast', onAwaToast);
});

const offRouterSuccess = router.on('success', (event) => {
    const page = (event as unknown as { detail: { page: { props: unknown } } }).detail.page;
    collectToasts(page.props);
});

onUnmounted(() => {
    offRouterSuccess();
    window.removeEventListener('awa-toast', onAwaToast);
});

function onAwaToast(event: Event) {
    const detail = (event as CustomEvent<{ type?: unknown; title?: unknown; messages?: unknown; message?: unknown }>).detail ?? {};
    const type = detail.type === 'error' ? 'error' : 'success';
    const messages = Array.isArray(detail.messages)
        ? detail.messages.map(String)
        : typeof detail.message === 'string' && detail.message !== ''
          ? [detail.message]
          : [];
    pushToast(type, typeof detail.title === 'string' ? detail.title : '', messages);
}

function linkClass(href: string): string {
    if (href === '/schools' || href === '/users') {
        const base = 'block rounded-md px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50';
        return isActive(href) ? `${base} bg-red-100 text-red-800` : base;
    }
    const base = 'block rounded-md px-3 py-2 text-sm font-medium';
    return isActive(href)
        ? `${base} bg-indigo-100 text-indigo-800`
        : `${base} text-gray-700 hover:bg-gray-100`;
}

function groupClass(group: NavGroup): string {
    if (group.danger) {
        const base = 'flex w-full items-center justify-between gap-1 rounded-md px-3 py-2 text-sm font-bold';
        return isGroupActive(group) || openGroups.value[group.label]
            ? `${base} bg-red-100 text-red-800`
            : `${base} text-red-700 hover:bg-red-50`;
    }
    const base = 'flex w-full items-center justify-between gap-1 rounded-md px-3 py-2 text-sm font-medium';
    return isGroupActive(group) || openGroups.value[group.label]
        ? `${base} bg-indigo-50 text-indigo-800`
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
                        <img :src="'/favicon.png'" alt="aWa logosu" class="h-8 w-8 object-contain" />
                        <span class="text-sm font-semibold leading-none text-gray-900">aWa | Okul Yönetim Sistemi</span>
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-gray-600 sm:inline">{{ user?.name }}</span>
                    <div v-if="currentSchool || mySchools.length > 0" class="relative">
                        <button
                            type="button"
                            title="Okul değiştir"
                            aria-label="Okul değiştir"
                            class="flex h-9 max-w-52 items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 text-sm font-semibold text-indigo-800 shadow-sm hover:bg-indigo-100 focus:border-indigo-500 focus:ring-indigo-500"
                            @click="schoolMenuOpen = !schoolMenuOpen"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" /></svg>
                            <span class="truncate">{{ currentSchool?.name ?? 'Okul seç' }}</span>
                            <svg v-if="mySchools.length > 1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div
                            v-if="schoolMenuOpen"
                            class="fixed inset-0 z-40"
                            @click="schoolMenuOpen = false"
                        ></div>
                        <div
                            v-if="schoolMenuOpen && mySchools.length > 1"
                            class="absolute right-0 z-50 mt-1 w-60 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl"
                            @keydown.escape="schoolMenuOpen = false"
                        >
                            <button
                                v-for="school in mySchools"
                                :key="school.id"
                                type="button"
                                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:bg-indigo-50"
                                :class="school.id === currentSchool?.id ? 'font-semibold text-indigo-800' : 'text-gray-700'"
                                @click="switchSchool(school.id)"
                            >
                                <svg v-if="school.id === currentSchool?.id" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                <span v-else class="h-4 w-4 shrink-0"></span>
                                <span class="min-w-0 flex-1 truncate">{{ school.name }}</span>
                                <span class="shrink-0 text-xs text-gray-400">{{ school.kurum_kodu }}</span>
                            </button>
                        </div>
                    </div>
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
                <template v-for="entry in nav" :key="entry.label">
                    <div v-if="isGroup(entry)">
                        <div :class="groupClass(entry)">
                            <img v-if="entry.icon === 'logo'" :src="'/kelebek.png'" alt="" aria-hidden="true" class="h-5 w-5 shrink-0 object-contain" />
                            <NavIcon v-else :name="entry.icon" />
                            <Link v-if="entry.href" :href="entry.href" class="min-w-0 flex-1 truncate text-left">
                                {{ entry.label }}
                            </Link>
                            <button v-else type="button" class="min-w-0 flex-1 cursor-pointer truncate text-left" @click="toggleGroup(entry.label)">
                                {{ entry.label }}
                            </button>
                            <button
                                type="button"
                                :aria-expanded="!!openGroups[entry.label]"
                                :aria-label="entry.label + ' menüsü'"
                                class="shrink-0 cursor-pointer rounded p-0.5"
                                @click="toggleGroup(entry.label)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="block h-4 w-4 transition-transform" :class="openGroups[entry.label] ? 'rotate-180' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                        </div>
                        <div v-show="openGroups[entry.label]" class="ml-3 mt-1 space-y-1 border-l-2 border-gray-100 pl-2">
                            <template v-for="child in entry.children" :key="child.label">
                                <span
                                    v-if="child.disabled"
                                    class="flex cursor-not-allowed items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-400"
                                    :title="child.label + ' (yakında)'"
                                >
                                    <NavIcon :name="child.icon" />
                                    {{ child.label }}
                                </span>
                                <Link v-else :href="child.href" :class="[linkClass(child.href), 'flex items-center gap-2']">
                                    <img v-if="child.icon === 'favicon'" :src="'/favicon.png'" alt="" aria-hidden="true" class="h-5 w-5 shrink-0 object-contain" />
                                    <NavIcon v-else :name="child.icon" />
                                    {{ child.label }}
                                </Link>
                            </template>
                        </div>
                    </div>
                    <Link v-else :href="entry.href" :class="[linkClass(entry.href), 'flex items-center gap-2']">
                        <NavIcon :name="entry.icon" />
                        {{ entry.label }}
                    </Link>
                </template>
            </nav>
        </header>

        <div class="flex gap-6 px-2 py-6">
            <aside class="sticky top-20 z-20 hidden w-56 shrink-0 self-start md:block print:hidden">
                <nav class="space-y-1 rounded-lg bg-white p-3 shadow-sm">
                    <template v-for="entry in nav" :key="entry.label">
                        <div v-if="isGroup(entry)">
                            <div :class="groupClass(entry)">
                                <img v-if="entry.icon === 'logo'" :src="'/kelebek.png'" alt="" aria-hidden="true" class="h-5 w-5 shrink-0 object-contain" />
                                <NavIcon v-else :name="entry.icon" />
                                <Link v-if="entry.href" :href="entry.href" class="min-w-0 flex-1 truncate text-left">
                                    {{ entry.label }}
                                </Link>
                                <button v-else type="button" class="min-w-0 flex-1 cursor-pointer truncate text-left" @click="toggleGroup(entry.label)">
                                    {{ entry.label }}
                                </button>
                                <button
                                    type="button"
                                    :aria-expanded="!!openGroups[entry.label]"
                                    :aria-label="entry.label + ' menüsü'"
                                    class="shrink-0 cursor-pointer rounded p-0.5"
                                    @click="toggleGroup(entry.label)"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="block h-4 w-4 transition-transform" :class="openGroups[entry.label] ? 'rotate-180' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                </button>
                            </div>
                            <div v-show="openGroups[entry.label]" class="ml-3 mt-1 space-y-1 border-l-2 border-gray-100 pl-2">
                                <template v-for="child in entry.children" :key="child.label">
                                    <span
                                        v-if="child.disabled"
                                        class="flex cursor-not-allowed items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-400"
                                        :title="child.label + ' (yakında)'"
                                    >
                                        <NavIcon :name="child.icon" />
                                        {{ child.label }}
                                    </span>
                                    <Link v-else :href="child.href" :class="[linkClass(child.href), 'flex items-center gap-2']">
                                        <img v-if="child.icon === 'favicon'" :src="'/favicon.png'" alt="" aria-hidden="true" class="h-5 w-5 shrink-0 object-contain" />
                                        <NavIcon v-else :name="child.icon" />
                                        {{ child.label }}
                                    </Link>
                                </template>
                            </div>
                        </div>
                        <Link v-else :href="entry.href" :class="[linkClass(entry.href), 'flex items-center gap-2']">
                            <NavIcon :name="entry.icon" />
                            {{ entry.label }}
                        </Link>
                    </template>
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
                class="flex animate-[awa-toast-in_250ms_ease] items-start gap-3 rounded-lg border-l-4 bg-white p-4 shadow-xl"
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
            class="pointer-events-none fixed bottom-2 left-3 z-50 select-none text-[20px] font-bold tracking-wide text-black"
        >
            made by <span class="font-bold text-black">m</span><span class="font-bold text-blue-900">ADEM</span><span class="font-bold text-black">atik</span>
        </div>
    </div>
</template>

<style>
@keyframes awa-toast-in {
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
