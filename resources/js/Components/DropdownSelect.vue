<script setup lang="ts">
import { computed, ref } from 'vue';

export interface SelectOption {
    value: string | number | null;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: SelectOption[];
        placeholder?: string;
        title?: string;
        ariaLabel?: string;
        id?: string;
    }>(),
    {
        placeholder: 'Seçin',
        title: undefined,
        ariaLabel: undefined,
        id: undefined,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | number | null): void;
    (e: 'change'): void;
}>();

const open = ref(false);

const selectedLabel = computed(
    () => props.options.find((option) => option.value === props.modelValue)?.label ?? props.placeholder,
);

function choose(value: string | number | null) {
    open.value = false;
    if (value !== props.modelValue) {
        emit('update:modelValue', value);
        emit('change');
    }
}
</script>

<template>
    <div class="relative">
        <button
            :id="id"
            type="button"
            :title="title ?? ariaLabel ?? placeholder"
            :aria-label="ariaLabel ?? title ?? placeholder"
            :aria-expanded="open"
            class="flex h-9 w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700 shadow-sm hover:bg-indigo-50 hover:text-indigo-800 focus:border-indigo-500 focus:ring-indigo-500"
            @click="open = !open"
        >
            <span class="truncate">{{ selectedLabel }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </button>
        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>
        <div
            v-if="open"
            class="absolute z-50 mt-1 max-h-60 w-full min-w-40 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-xl"
            @keydown.escape="open = false"
        >
            <button
                v-for="option in options"
                :key="String(option.value)"
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-indigo-50"
                :class="option.value === modelValue ? 'font-semibold text-indigo-800' : 'text-gray-700'"
                @click="choose(option.value)"
            >
                <svg v-if="option.value === modelValue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                <span v-else class="h-4 w-4 shrink-0"></span>
                <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
            </button>
        </div>
    </div>
</template>
