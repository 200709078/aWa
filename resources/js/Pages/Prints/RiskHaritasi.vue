<script setup lang="ts">
import PrintLayout from '../../Layouts/PrintLayout.vue';

interface Row {
    no: number;
    school_number: string;
    full_name: string;
    marks: Record<string, boolean>;
}

defineProps<{
    branch: { id: number; name: string };
    year: { id: number; name: string };
    criteria: Record<string, string>;
    rows: Row[];
    totals: Record<string, number>;
    school: { name: string };
}>();
</script>

<template>
    <PrintLayout title="Sınıf Risk Haritası" back-href="/bilgi-formlari">
        <div class="no-print mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">
            Yazdırırken yönlendirme <strong>Yatay</strong>, kenar boşlukları <strong>Yok</strong>, ölçek <strong>%100</strong>,
            <strong>Arka plan grafikleri açık</strong> olmalı. ✓ işaretli hücreler form verisinden otomatik geldi; boş hücreler elle işaretlenir.
        </div>

        <div class="risk-map mx-auto bg-white text-gray-900">
            <div class="flex items-baseline justify-between border-b-2 border-gray-400 pb-2">
                <h1 class="text-lg font-bold uppercase">{{ branch.name }} Sınıfı Risk Haritası</h1>
                <div class="text-xs text-gray-600">{{ year.name }} · {{ school.name }}</div>
            </div>

            <table class="risk-table mt-3">
                <thead>
                    <tr>
                        <th class="w-8">No</th>
                        <th class="w-16">Okul No</th>
                        <th class="name-col">Ad Soyad</th>
                        <th v-for="(label, key) in criteria" :key="key" class="crit">
                            <span class="crit-label">{{ label }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.no">
                        <td class="text-center">{{ row.no }}</td>
                        <td class="text-center">{{ row.school_number }}</td>
                        <td class="name-col">{{ row.full_name }}</td>
                        <td v-for="(label, key) in criteria" :key="key" class="text-center">
                            <span v-if="row.marks[key]" class="font-bold">✓</span>
                        </td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" class="text-right font-bold">TOPLAM</td>
                        <td v-for="(label, key) in criteria" :key="key" class="text-center font-bold">
                            {{ totals[key] > 0 ? totals[key] : '' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PrintLayout>
</template>

<style>
.risk-map {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
.risk-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9px;
    line-height: 1.2;
}
.risk-table th,
.risk-table td {
    border: 1px solid #b3541e;
    padding: 2px 3px;
}
.risk-table thead th {
    background-color: #e8a04c;
}
.risk-table .total-row td {
    background-color: #f6e3c8;
}
.risk-table .name-col {
    min-width: 110px;
    max-width: 140px;
}
.risk-table th.crit {
    height: 150px;
    vertical-align: bottom;
    white-space: nowrap;
}
.risk-table .crit-label {
    display: inline-block;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    max-height: 145px;
    overflow: hidden;
}
@page {
    size: A4 landscape;
}
</style>
