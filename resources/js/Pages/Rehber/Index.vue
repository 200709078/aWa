<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';

interface Year {
    id: number;
    name: string;
    is_active: boolean;
}

interface Branch {
    id: number;
    name: string;
}

interface Summary {
    students: number;
    guardians: number;
    cards: number;
    without_phone: number;
}

const props = defineProps<{
    years: Year[];
    activeYearId: number | null;
}>();

const yearId = ref<number | null>(props.activeYearId);
const branches = ref<Branch[]>([]);
const selectedBranches = ref<number[]>([]);
const kind = ref<'all' | 'student' | 'guardian'>('all');
const withPhoto = ref(true);
const summary = ref<Summary | null>(null);
const loading = ref(false);
const error = ref('');

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function loadBranches() {
    branches.value = [];
    selectedBranches.value = [];
    summary.value = null;
    if (!yearId.value) return;
    const response = await fetch(`/rehber-aktarma/yillar/${yearId.value}/subeler`, {
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) return;
    branches.value = (await response.json()) as Branch[];
    selectedBranches.value = branches.value.map((b) => b.id);
}

async function showSummary() {
    error.value = '';
    summary.value = null;
    if (!yearId.value) {
        error.value = 'Akademik yıl seçin.';
        return;
    }
    if (selectedBranches.value.length === 0) {
        error.value = 'En az bir şube seçin.';
        return;
    }
    loading.value = true;
    try {
        const response = await fetch('/rehber-aktarma/ozet', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                academic_year_id: yearId.value,
                branch_ids: selectedBranches.value,
                type: kind.value,
            }),
        });
        if (!response.ok) throw new Error('Özet alınamadı.');
        summary.value = (await response.json()) as Summary;
    } catch {
        error.value = 'Özet alınamadı. Tekrar deneyin.';
    } finally {
        loading.value = false;
    }
}

const downloadUrl = computed(() => {
    const params = new URLSearchParams();
    if (yearId.value) params.append('academic_year_id', String(yearId.value));
    for (const id of selectedBranches.value) params.append('branch_ids[]', String(id));
    params.append('type', kind.value);
    params.append('photo', withPhoto.value ? '1' : '0');
    return `/rehber-aktarma/indir?${params.toString()}`;
});

const excelUrl = computed(() => {
    const params = new URLSearchParams();
    if (yearId.value) params.append('academic_year_id', String(yearId.value));
    for (const id of selectedBranches.value) params.append('branch_ids[]', String(id));
    params.append('type', kind.value);
    return `/rehber-aktarma/excel?${params.toString()}`;
});

const canDownload = computed(() => !!yearId.value && selectedBranches.value.length > 0);

watch(yearId, () => {
    void loadBranches();
});

void loadBranches();
</script>

<template>
    <AppLayout title="Rehber Aktarma">
        <div class="max-w-xl rounded-lg bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Rehber Aktarma — VCF</h1>
            <p class="mt-2 text-sm text-gray-600">
                Seçili şubelerdeki öğrenci ve veli telefonlarını VCF formatında indirin.
                Telefonu olmayan kişiler dosyaya yazılmaz.
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="vcf-year" class="block text-sm font-medium text-gray-700">Akademik Yıl</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="vcf-year"
                            v-model="yearId"
                            :options="[{ value: null, label: 'Seçin' }, ...years.map((year) => ({ value: year.id, label: `${year.name}${year.is_active ? ' (aktif)' : ''}` }))]"
                            aria-label="Akademik Yıl"
                        />
                    </div>
                </div>

                <div>
                    <span class="block text-sm font-medium text-gray-700">Şubeler</span>
                    <div class="mt-1 flex flex-wrap gap-3">
                        <label v-for="branch in branches" :key="branch.id" class="flex items-center gap-1 text-sm text-gray-700">
                            <input v-model="selectedBranches" type="checkbox" :value="branch.id" class="rounded border-gray-300" />
                            {{ branch.name }}
                        </label>
                        <p v-if="branches.length === 0" class="text-sm text-gray-400">Önce akademik yıl seçin.</p>
                    </div>
                </div>

                <div>
                    <span class="block text-sm font-medium text-gray-700">Kayıt Tipi</span>
                    <div class="mt-1 flex gap-4 text-sm text-gray-700">
                        <label class="flex items-center gap-1">
                            <input v-model="kind" type="radio" value="all" class="border-gray-300" /> Tümü
                        </label>
                        <label class="flex items-center gap-1">
                            <input v-model="kind" type="radio" value="student" class="border-gray-300" /> Öğrenci
                        </label>
                        <label class="flex items-center gap-1">
                            <input v-model="kind" type="radio" value="guardian" class="border-gray-300" /> Veli
                        </label>
                    </div>
                </div>

                <label class="flex items-center gap-1 text-sm text-gray-700">
                    <input v-model="withPhoto" type="checkbox" class="rounded border-gray-300" />
                    Fotoğrafları dahil et
                </label>

                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :disabled="loading || !canDownload"
                        class="rounded-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500 disabled:opacity-100"
                        @click="showSummary"
                    >
                        Özeti Göster
                    </button>
                    <a
                        :href="canDownload ? downloadUrl : undefined"
                        :aria-disabled="!canDownload"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                        :class="{ 'pointer-events-none bg-gray-300 text-gray-500': !canDownload }"
                    >
                        VCF İndir
                    </a>
                    <a
                        :href="canDownload ? excelUrl : undefined"
                        :aria-disabled="!canDownload"
                        class="rounded-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                        :class="{ 'pointer-events-none bg-gray-300 text-gray-500': !canDownload }"
                    >
                        Excele Dışa Aktar
                    </a>
                </div>

                <dl v-if="summary" class="divide-y divide-gray-200">
                    <div class="flex justify-between py-2">
                        <dt class="text-gray-600">Öğrenci</dt>
                        <dd class="font-semibold">{{ summary.students }}</dd>
                    </div>
                    <div class="flex justify-between py-2">
                        <dt class="text-gray-600">Veli</dt>
                        <dd class="font-semibold">{{ summary.guardians }}</dd>
                    </div>
                    <div class="flex justify-between py-2">
                        <dt class="text-gray-600">Dosyaya yazılacak kart</dt>
                        <dd class="font-semibold text-green-700">{{ summary.cards }}</dd>
                    </div>
                    <div class="flex justify-between py-2">
                        <dt class="text-gray-600">Telefonu olmadığı için atlanacak</dt>
                        <dd class="font-semibold text-yellow-700">{{ summary.without_phone }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
