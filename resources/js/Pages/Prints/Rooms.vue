<script setup lang="ts">
import PrintLayout from '../../Layouts/PrintLayout.vue';

interface RoomList {
    room: { id: number; name: string };
    students: { seat: string; school_number: string; full_name: string; branch: string | null }[];
}

defineProps<{
    plan: { id: number; name: string | null };
    week: { name: string };
    lists: RoomList[];
}>();
</script>

<template>
    <PrintLayout title="Salon Öğrenci Listeleri" :back-href="`/distribution/plans/${plan.id}`">
        <h1 class="text-xl font-bold print:text-lg">
            Salon Öğrenci Listeleri — {{ week.name }}{{ plan.name ? ` · ${plan.name}` : '' }}
        </h1>

        <div
            v-for="(list, index) in lists"
            :key="list.room.id"
            :class="index < lists.length - 1 ? 'print:break-after-page' : ''"
            class="mt-6 print:mt-2 print:break-inside-avoid print:pb-[8mm]"
        >
            <h2 class="text-lg font-semibold print:text-base">{{ list.room.name }} ({{ list.students.length }} öğrenci)</h2>
            <table class="mt-2 w-full border-collapse text-sm print:mt-1 print:text-xs">
                <thead>
                    <tr class="border-b-2 border-gray-800">
                        <th class="py-1 pr-4 text-left print:py-0.5">Okul No</th>
                        <th class="py-1 pr-4 text-left print:py-0.5">Ad Soyad</th>
                        <th class="py-1 text-left print:py-0.5">Sınıf</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="student in list.students" :key="student.school_number" class="border-b border-gray-200">
                        <td class="py-1 pr-4 print:py-0.5">{{ student.school_number }}</td>
                        <td class="py-1 pr-4 print:py-0.5">{{ student.full_name }}</td>
                        <td class="py-1 print:py-0.5">{{ student.branch }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PrintLayout>
</template>
