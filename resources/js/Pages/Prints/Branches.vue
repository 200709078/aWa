<script setup lang="ts">
import PrintLayout from '../../Layouts/PrintLayout.vue';

interface BranchGroup {
    branch: string;
    students: { school_number: string; full_name: string; room: string; seat: string }[];
}

defineProps<{
    plan: { id: number; name: string | null };
    week: { name: string };
    groups: BranchGroup[];
}>();
</script>

<template>
    <PrintLayout title="Sınıf Bazında Sınav Yeri Listesi" :back-href="`/distribution/plans/${plan.id}`">
        <h1 class="text-xl font-bold">
            Sınıf Bazında Sınav Yeri Listesi — {{ week.name }}{{ plan.name ? ` · ${plan.name}` : '' }}
        </h1>

        <div
            v-for="(group, index) in groups"
            :key="group.branch"
            :class="index < groups.length - 1 ? 'print:break-after-page' : ''"
            class="mt-6 print:pb-[8mm]"
        >
            <h2 class="text-lg font-semibold">{{ group.branch }} ({{ group.students.length }} öğrenci)</h2>
            <table class="mt-2 w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b-2 border-gray-800">
                        <th class="py-1 pr-4 text-left">Okul No</th>
                        <th class="py-1 pr-4 text-left">Ad Soyad</th>
                        <th class="py-1 pr-4 text-left">Salon</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="student in group.students" :key="student.school_number" class="border-b border-gray-200">
                        <td class="py-1 pr-4">{{ student.school_number }}</td>
                        <td class="py-1 pr-4">{{ student.full_name }}</td>
                        <td class="py-1 pr-4">{{ student.room }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PrintLayout>
</template>
