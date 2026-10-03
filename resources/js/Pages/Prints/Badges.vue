<script setup lang="ts">
import { computed, ref } from 'vue';
import BadgeCard from '../../Components/BadgeCard.vue';
import PrintLayout from '../../Layouts/PrintLayout.vue';

interface BadgeTeacher {
    id: number;
    full_name: string;
    title: string;
}

const props = defineProps<{
    teachers: BadgeTeacher[];
    school: { name: string; logo_url: string | null };
}>();

function querySize(key: string, fallback: number): number {
    const v = Number(new URLSearchParams(window.location.search).get(key));
    return Number.isFinite(v) && v > 0 ? v : fallback;
}

const cardWidth = ref(querySize('w', 85));
const cardHeight = ref(querySize('h', 54));

const widthMm = computed(() => `${Math.min(120, Math.max(50, Number(cardWidth.value) || 85))}mm`);
const heightMm = computed(() => `${Math.min(90, Math.max(30, Number(cardHeight.value) || 54))}mm`);
</script>

<template>
    <PrintLayout title="Yaka Kartları" back-href="/personel">
        <div class="no-print mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">
            Yazdırırken: Kenar boşlukları <strong>Yok</strong>, Ölçek <strong>%100</strong>, <strong>Arka plan grafikleri açık</strong> olmalı.
            Her satırda 1 kişi (solda ön yüz, sağda arka yüz). Kesip arkalı önlü kılıfa koyun.
        </div>

        <h1 class="text-xl font-bold print:text-base">Yaka Kartları — {{ school.name }} ({{ teachers.length }} kişi)</h1>

        <div v-if="teachers.length === 0" class="mt-6 rounded-lg bg-white p-6 text-center text-gray-500 shadow-sm">
            Seçili personel bulunamadı. <a href="/personel" class="text-indigo-600 hover:underline">Personel listesine dönüp</a> seçim yapın.
        </div>

        <div v-else class="mt-4 space-y-[5mm]">
            <BadgeCard
                v-for="teacher in teachers"
                :key="teacher.id"
                :full-name="teacher.full_name"
                :title="teacher.title"
                :school-name="school.name"
                :logo-url="school.logo_url"
                :width-mm="widthMm"
                :height-mm="heightMm"
            />
        </div>
    </PrintLayout>
</template>
