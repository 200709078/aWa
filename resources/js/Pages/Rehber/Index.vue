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
const selectedBranch = ref<number | null>(null);
const kind = ref<'all' | 'student' | 'guardian'>('all');
const withPhoto = ref(true);
const summary = ref<Summary | null>(null);
const error = ref('');

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function loadBranches() {
    branches.value = [];
    selectedBranch.value = null;
    summary.value = null;
    if (!yearId.value) return;
    const response = await fetch(`/rehber-aktarma/yillar/${yearId.value}/subeler`, {
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) return;
    branches.value = (await response.json()) as Branch[];
    await showSummary();
}

function branchIds(): number[] {
    if (selectedBranch.value) return [selectedBranch.value];
    return branches.value.map((b) => b.id);
}

async function showSummary() {
    error.value = '';
    summary.value = null;
    if (!yearId.value) {
        error.value = 'Akademik yıl seçin.';
        return;
    }
    const ids = branchIds();
    if (ids.length === 0) {
        error.value = 'Bu yılda şube yok.';
        return;
    }
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
                branch_ids: ids,
                type: kind.value,
            }),
        });
        if (!response.ok) throw new Error('Özet alınamadı.');
        summary.value = (await response.json()) as Summary;
    } catch {
        error.value = 'Özet alınamadı. Tekrar deneyin.';
    }
}

const downloadUrl = computed(() => {
    const params = new URLSearchParams();
    if (yearId.value) params.append('academic_year_id', String(yearId.value));
    for (const id of branchIds()) params.append('branch_ids[]', String(id));
    params.append('type', kind.value);
    params.append('photo', withPhoto.value ? '1' : '0');
    return `/rehber-aktarma/indir?${params.toString()}`;
});

const excelUrl = computed(() => {
    const params = new URLSearchParams();
    if (yearId.value) params.append('academic_year_id', String(yearId.value));
    for (const id of branchIds()) params.append('branch_ids[]', String(id));
    params.append('type', kind.value);
    return `/rehber-aktarma/excel?${params.toString()}`;
});

const canDownload = computed(() => !!yearId.value && branchIds().length > 0);

watch([selectedBranch, kind], () => {
    void showSummary();
});

function notifyDownload(message: string) {
    window.dispatchEvent(
        new CustomEvent('kelebek-toast', {
            detail: { type: 'success', messages: [message] },
        }),
    );
}

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
                    <label for="vcf-branch" class="block text-sm font-medium text-gray-700">Sınıflar</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="vcf-branch"
                            v-model="selectedBranch"
                            :options="[{ value: null, label: 'Tümü' }, ...branches.map((branch) => ({ value: branch.id, label: branch.name }))]"
                            aria-label="Sınıflar"
                        />
                    </div>
                </div>

                <div>
                    <label for="vcf-kind" class="block text-sm font-medium text-gray-700">Kayıt Tipi</label>
                    <div class="mt-1">
                        <DropdownSelect
                            id="vcf-kind"
                            v-model="kind"
                            :options="[
                                { value: 'all', label: 'Tümü' },
                                { value: 'student', label: 'Öğrenci' },
                                { value: 'guardian', label: 'Veli' },
                            ]"
                            aria-label="Kayıt Tipi"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex h-9 items-center gap-2">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="withPhoto"
                            aria-label="Fotoğrafları dahil et"
                            class="flex h-6 w-11 shrink-0 items-center rounded-full px-0.5 transition-colors"
                            :class="withPhoto ? 'bg-indigo-600' : 'bg-gray-300'"
                            @click="withPhoto = !withPhoto"
                        >
                            <span
                                class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                                :class="withPhoto ? 'translate-x-5' : 'translate-x-0'"
                            ></span>
                        </button>
                        <span class="text-sm text-gray-700">Fotoğraflar</span>
                    </div>
                    <a
                        :href="canDownload ? downloadUrl : undefined"
                        :aria-disabled="!canDownload"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                        :class="{ 'pointer-events-none bg-gray-300 text-gray-500': !canDownload }"
                        @click="notifyDownload('VCF dosyası indiriliyor.')"
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

                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

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
                        <dt class="text-gray-600">Telefonu olmayan</dt>
                        <dd class="font-semibold text-yellow-700">{{ summary.without_phone }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
