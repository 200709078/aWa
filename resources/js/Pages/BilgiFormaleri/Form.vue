<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import BilgiGuardianForm from '../../Components/BilgiGuardianForm.vue';
import DropdownSelect from '../../Components/DropdownSelect.vue';
import { blankGuardianBlock, relationOptions, type GuardianBlock } from '../../types/bilgiFormu';
import { bloodTypeOptions, genderOptions } from '../../types/studentForm';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps<{
    student: { id: number; school_number: string; branch_name: string; full_name: string };
    person: {
        email: string; phone: string; address: string; gender: string | null;
        birth_place: string; birth_date: string; blood_type: string | null; religion: string;
        height_cm: number | null; weight_kg: number | null; chronic_illness: string;
    };
    guardian: GuardianBlock;
    guardian_relation: string;
    mother: GuardianBlock;
    father: GuardianBlock;
    form: Partial<Record<string, string | number | null>>;
    backUrl: string;
}>();

const str = (v: string | number | null | undefined): string => (v === null || v === undefined ? '' : String(v));
const num = (v: string | number | null | undefined): number | null => {
    if (v === null || v === undefined || v === '') return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
};

const form = useForm({
    person: {
        email: str(props.person.email),
        phone: str(props.person.phone),
        address: str(props.person.address),
        gender: props.person.gender ?? null,
        birth_place: str(props.person.birth_place),
        birth_date: str(props.person.birth_date),
        blood_type: props.person.blood_type ?? null,
        religion: str(props.person.religion),
        height_cm: num(props.person.height_cm),
        weight_kg: num(props.person.weight_kg),
        chronic_illness: str(props.person.chronic_illness),
    },
    guardian_relation: props.guardian_relation,
    guardian: { ...blankGuardianBlock(), ...props.guardian },
    mother: { ...blankGuardianBlock(), ...props.mother },
    father: { ...blankGuardianBlock(), ...props.father },
    form: {
        earthquake_loss: str(props.form.earthquake_loss),
        family_income: str(props.form.family_income),
        transport: str(props.form.transport),
        free_lunch: str(props.form.free_lunch),
        martyr_child: str(props.form.martyr_child),
        preschool: str(props.form.preschool),
        medication: str(props.form.medication),
        medical_device: str(props.form.medical_device),
        hobbies: str(props.form.hobbies),
        moved: str(props.form.moved),
        changed_school: str(props.form.changed_school),
        extracurricular: str(props.form.extracurricular),
        tech_devices: str(props.form.tech_devices),
        trauma: str(props.form.trauma),
        sibling_count: num(props.form.sibling_count),
        birth_order: num(props.form.birth_order),
        school_siblings: num(props.form.school_siblings),
        family_disability: str(props.form.family_disability),
        family_illness: str(props.form.family_illness),
        household: str(props.form.household),
        notes: str(props.form.notes),
    },
});

const textFields: { key: string; label: string }[] = [
    { key: 'earthquake_loss', label: 'Depremde ebeveyn kaybı' },
    { key: 'family_income', label: 'Aile gelir durumu' },
    { key: 'transport', label: 'Taşıma' },
    { key: 'free_lunch', label: 'Ücretsiz öğle yemeği' },
    { key: 'martyr_child', label: 'Şehit/gazi çocuğu' },
    { key: 'preschool', label: 'Okul öncesi eğitim' },
    { key: 'medication', label: 'Sürekli ilaç' },
    { key: 'medical_device', label: 'Tıbbi cihaz' },
    { key: 'hobbies', label: 'Hobiler' },
    { key: 'moved', label: 'Yakın zamanda taşındı mı' },
    { key: 'changed_school', label: 'Okul değiştirdi mi' },
    { key: 'extracurricular', label: 'Ders dışı faaliyetler' },
    { key: 'tech_devices', label: 'Teknolojik cihazlar' },
    { key: 'trauma', label: 'Travmatik olay' },
    { key: 'family_disability', label: 'Ailede engel' },
    { key: 'family_illness', label: 'Ailede sürekli hastalık' },
    { key: 'household', label: 'Evde yaşayanlar' },
    { key: 'notes', label: 'Notlar' },
];

function submit() {
    form.transform((data) => ({ ...data, _method: 'put' })).post(`/bilgi-formlari/${props.student.id}`);
}
</script>

<template>
    <AppLayout :title="`Bilgi Formu — ${student.full_name}`">
        <div class="w-full max-w-[80%] rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Bilgi Formu — {{ student.full_name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ student.branch_name }} · No {{ student.school_number }} (kimlik bilgileri Öğrenciler ekranından değişir)</p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        form="bilgi-form"
                        title="Kaydet"
                        aria-label="Kaydet"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </button>
                    <Link
                        :href="backUrl"
                        title="Geri dön"
                        aria-label="Geri dön"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 shadow-sm hover:bg-indigo-100 hover:text-indigo-800"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </Link>
                </div>
            </div>

            <form id="bilgi-form" class="mt-6 space-y-8" @submit.prevent="submit">
                <section>
                    <h2 class="text-lg font-semibold text-gray-900">Öğrenci</h2>
                    <div class="mt-2 grid grid-cols-3 gap-3">
                        <div>
                            <label for="bf-email" class="block text-sm font-medium text-gray-700">E-posta</label>
                            <input id="bf-email" v-model="form.person.email" type="email" maxlength="100" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label for="bf-phone" class="block text-sm font-medium text-gray-700">Telefon</label>
                            <input id="bf-phone" v-model="form.person.phone" type="text" maxlength="30" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <span class="block text-sm font-medium text-gray-700">Cinsiyet</span>
                            <div class="mt-1"><DropdownSelect id="bf-gender" v-model="form.person.gender" :options="genderOptions" aria-label="Cinsiyet" /></div>
                        </div>
                        <div>
                            <label for="bf-bplace" class="block text-sm font-medium text-gray-700">Doğum Yeri</label>
                            <input id="bf-bplace" v-model="form.person.birth_place" type="text" maxlength="100" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label for="bf-bdate" class="block text-sm font-medium text-gray-700">Doğum Tarihi</label>
                            <input id="bf-bdate" v-model="form.person.birth_date" type="date" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <span class="block text-sm font-medium text-gray-700">Kan Grubu</span>
                            <div class="mt-1"><DropdownSelect id="bf-blood" v-model="form.person.blood_type" :options="bloodTypeOptions" aria-label="Kan Grubu" /></div>
                        </div>
                        <div>
                            <label for="bf-religion" class="block text-sm font-medium text-gray-700">Din</label>
                            <input id="bf-religion" v-model="form.person.religion" type="text" maxlength="50" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label for="bf-height" class="block text-sm font-medium text-gray-700">Boy (cm)</label>
                            <input id="bf-height" v-model.number="form.person.height_cm" type="number" min="50" max="250" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label for="bf-weight" class="block text-sm font-medium text-gray-700">Kilo (kg)</label>
                            <input id="bf-weight" v-model.number="form.person.weight_kg" type="number" min="15" max="300" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div>
                            <label for="bf-address" class="block text-sm font-medium text-gray-700">Adres</label>
                            <textarea id="bf-address" v-model="form.person.address" rows="2" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                        <div>
                            <label for="bf-ill" class="block text-sm font-medium text-gray-700">Sürekli Hastalık</label>
                            <textarea id="bf-ill" v-model="form.person.chronic_illness" rows="2" maxlength="255" class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900">Veli</h2>
                    <div class="mt-2 max-w-xs">
                        <span class="block text-sm font-medium text-gray-700">Velinin Yakınlığı</span>
                        <div class="mt-1"><DropdownSelect id="bf-grel" v-model="form.guardian_relation" :options="relationOptions" aria-label="Veli Yakınlığı" /></div>
                    </div>
                    <div class="mt-2"><BilgiGuardianForm :model="form.guardian" id-prefix="bf-g" title="Veli Bilgisi" /></div>
                </section>

                <section class="grid grid-cols-2 gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Anne</h2>
                        <div class="mt-2"><BilgiGuardianForm :model="form.mother" id-prefix="bf-m" title="Anne Bilgisi" /></div>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Baba</h2>
                        <div class="mt-2"><BilgiGuardianForm :model="form.father" id-prefix="bf-f" title="Baba Bilgisi" /></div>
                    </div>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-gray-900">Aile ve Diğer Bilgiler</h2>
                    <div class="mt-2 grid grid-cols-3 gap-3">
                        <div>
                            <label for="bf-sib" class="block text-sm font-medium text-gray-700">Kaç kardeş</label>
                            <input id="bf-sib" v-model.number="form.form.sibling_count" type="number" min="0" max="30" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label for="bf-order" class="block text-sm font-medium text-gray-700">Kaçıncı çocuk</label>
                            <input id="bf-order" v-model.number="form.form.birth_order" type="number" min="0" max="30" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label for="bf-ssib" class="block text-sm font-medium text-gray-700">Okula giden kardeş</label>
                            <input id="bf-ssib" v-model.number="form.form.school_siblings" type="number" min="0" max="30" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div v-for="f in textFields" :key="f.key">
                            <label :for="`bf-${f.key}`" class="block text-sm font-medium text-gray-700">{{ f.label }}</label>
                            <input :id="`bf-${f.key}`" v-model="(form.form as Record<string, unknown>)[f.key]" type="text" class="mt-1 block h-9 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>
                </section>
            </form>
        </div>
    </AppLayout>
</template>
