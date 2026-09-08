<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        photoUrl?: string | null;
        fullName?: string;
        imgClass?: string;
        placeholderClass?: string;
        circleClass?: string;
    }>(),
    {
        photoUrl: null,
        fullName: '',
        imgClass: '',
        placeholderClass: '',
        circleClass: '',
    },
);

const initials = computed(() => {
    const parts = props.fullName.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toLocaleUpperCase('tr');
    return (parts[0][0] + parts[parts.length - 1][0]).toLocaleUpperCase('tr');
});

const title = computed(() =>
    props.fullName ? `${props.fullName} — fotoğraf yok` : 'Fotoğraf yok',
);
</script>

<template>
    <img
        v-if="photoUrl"
        :src="photoUrl"
        :alt="fullName"
        loading="lazy"
        :class="imgClass"
    />
    <div
        v-else
        :class="['flex aspect-[3/4] items-center justify-center rounded bg-gray-100', placeholderClass]"
        :title="title"
        role="img"
        aria-label="Fotoğraf yok"
    >
        <div
            :class="[
                'flex aspect-square items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 via-indigo-600 to-violet-600 font-bold text-white shadow-md ring-2 ring-indigo-100 print:border-2 print:border-gray-400 print:bg-none print:text-gray-600 print:shadow-none print:ring-0',
                circleClass,
            ]"
        >
            <span>{{ initials }}</span>
        </div>
    </div>
</template>
