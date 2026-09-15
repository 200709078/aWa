<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PrintLayout from '../../Layouts/PrintLayout.vue';

const schoolName = computed(() => {
    const pageProps = usePage().props as unknown as { current_school: { name: string } | null };
    return pageProps.current_school?.name ?? '';
});

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
        <h1 class="text-xl font-bold print:text-lg">
            Sınıf Bazında Sınav Yeri Listesi - {{ schoolName }} - {{ week.name }}{{ plan.name ? ` · ${plan.name}` : '' }}
        </h1>

        <div
            v-for="(group, index) in groups"
            :key="group.branch"
            :class="index < groups.length - 1 ? 'print:break-after-page' : ''"
            class="mt-6 print:mt-2 print:break-inside-avoid print:pb-[8mm]"
        >
            <h2 class="text-lg font-semibold print:text-base">{{ group.branch }} ({{ group.students.length }} öğrenci)</h2>
            <table class="mt-2 w-full border-collapse text-sm print:mt-1 print:text-xs">
                <thead>
                    <tr class="border-b-2 border-gray-800">
                        <th class="py-1 pr-4 text-left print:py-0.5">Okul No</th>
                        <th class="py-1 pr-4 text-left print:py-0.5">Ad Soyad</th>
                        <th class="py-1 pr-4 text-left print:py-0.5">Salon</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="student in group.students" :key="student.school_number" class="border-b border-gray-200">
                        <td class="py-1 pr-4 print:py-0.5">{{ student.school_number }}</td>
                        <td class="py-1 pr-4 print:py-0.5">{{ student.full_name }}</td>
                        <td class="py-1 pr-4 print:py-0.5">{{ student.room }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PrintLayout>
</template>
