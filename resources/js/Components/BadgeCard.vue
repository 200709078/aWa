<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    fullName: string;
    title: string;
    schoolName: string;
    logoUrl: string | null;
    widthMm: string;
    heightMm: string;
}>();

const schoolShort = computed(() =>
    props.schoolName
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => [...w][0] ?? '')
        .join('')
        .toLocaleUpperCase('tr-TR'),
);

function nameSizeStyle(fullName: string): string {
    if (fullName.length > 24) return 'font-size:6.3mm;';
    if (fullName.length > 16) return 'font-size:7.5mm;';
    return 'font-size:8.5mm;';
}

function titleSizeStyle(title: string): string {
    if (title.length > 30) return 'font-size:4.4mm;';
    if (title.length > 20) return 'font-size:5.1mm;';
    return 'font-size:5.7mm;';
}
</script>

<template>
    <div class="badge-row flex flex-wrap gap-[5mm] break-inside-avoid">
        <!-- Ön yüz -->
        <div
            class="badge-card rounded-[2mm] border-2 border-gray-300 bg-white"
            :style="{ width: props.widthMm, height: props.heightMm }"
        >
            <div class="flex h-full flex-col p-[2mm]">
                <div class="relative flex items-center">
                    <img
                        v-if="props.logoUrl"
                        :src="props.logoUrl"
                        alt="Okul logosu"
                        class="absolute left-0 h-[15.8mm] w-[11.6mm] shrink-0 rounded-full border border-gray-200 object-cover"
                    />
                    <div
                        v-else
                        class="absolute left-0 flex h-[15.8mm] w-[11.6mm] shrink-0 items-center justify-center rounded-full bg-gray-200 text-[2.5mm] font-semibold text-gray-500"
                    >
                        LOGO
                    </div>
                    <img
                        v-if="props.logoUrl"
                        :src="props.logoUrl"
                        alt="Okul logosu"
                        class="absolute right-0 h-[15.8mm] w-[11.6mm] shrink-0 rounded-full border border-gray-200 object-cover"
                    />
                    <div
                        v-else
                        class="absolute right-0 flex h-[15.8mm] w-[11.6mm] shrink-0 items-center justify-center rounded-full bg-gray-200 text-[2.5mm] font-semibold text-gray-500"
                    >
                        LOGO
                    </div>
                    <div
                        class="w-full text-center font-bold uppercase leading-tight text-indigo-900"
                        style="font-size: 12.1mm"
                    >
                        {{ schoolShort }}
                    </div>
                </div>
                <div
                    class="mt-[3mm] flex flex-1 items-center justify-center rounded-[1mm] bg-red-600 px-[2mm] text-center font-extrabold uppercase leading-[1.05] text-white"
                    style="font-size: 13.2mm"
                >
                    Nöbetçi<br />Öğretmen
                </div>
            </div>
        </div>

        <!-- Arka yüz -->
        <div
            class="badge-card rounded-[2mm] border-2 border-gray-300 bg-white"
            :style="{ width: props.widthMm, height: props.heightMm }"
        >
            <div class="flex h-full flex-col p-[3mm]">
                <div class="relative flex items-center">
                            <img
                                v-if="props.logoUrl"
                                :src="props.logoUrl"
                                alt="Okul logosu"
                                class="absolute left-0 h-[15.8mm] w-[11.6mm] shrink-0 rounded-full border border-gray-200 object-cover"
                            />
                            <div
                                v-else
                                class="absolute left-0 flex h-[15.8mm] w-[11.6mm] shrink-0 items-center justify-center rounded-full bg-gray-200 text-[2.5mm] font-semibold text-gray-500"
                            >
                                LOGO
                            </div>
                            <img
                                v-if="props.logoUrl"
                                :src="props.logoUrl"
                                alt="Okul logosu"
                                class="absolute right-0 h-[15.8mm] w-[11.6mm] shrink-0 rounded-full border border-gray-200 object-cover"
                            />
                            <div
                                v-else
                                class="absolute right-0 flex h-[15.8mm] w-[11.6mm] shrink-0 items-center justify-center rounded-full bg-gray-200 text-[2.5mm] font-semibold text-gray-500"
                            >
                                LOGO
                            </div>
                            <div
                                class="w-full text-center font-bold uppercase leading-tight text-indigo-900"
                                style="font-size: 12.1mm"
                            >
                                {{ schoolShort }}
                            </div>
                </div>
                <div class="flex flex-1 flex-col items-center justify-center px-[2mm] text-center">
                            <div class="break-words font-extrabold uppercase leading-tight text-gray-900" :style="nameSizeStyle(props.fullName)">
                        {{ props.fullName }}
                    </div>
                    <div class="mt-[1.5mm] font-semibold uppercase leading-tight text-gray-600" :style="titleSizeStyle(props.title)">
                        {{ props.title }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.badge-card {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}

@media print {
    .badge-row {
        break-inside: avoid;
    }
}
</style>
