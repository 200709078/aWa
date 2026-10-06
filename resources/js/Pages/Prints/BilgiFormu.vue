<script setup lang="ts">
import PrintLayout from '../../Layouts/PrintLayout.vue';

interface GuardianPrint {
    name: string;
    relation: string;
    phone: string;
    education: string;
    job: string;
}

interface ParentPrint {
    name: string;
    phone: string;
    birth: string;
    alive: string;
    biological: string;
    disability: string;
    education: string;
    job: string;
}

interface FormPrint {
    full_name: string;
    gender: string;
    number: string;
    branch: string;
    birth: string;
    address: string;
    preschool: string;
    medication: string;
    hobbies: string;
    illness: string;
    moved: string;
    extracurricular: string;
    tech: string;
    trauma: string;
    guardian: GuardianPrint;
    mother: ParentPrint;
    father: ParentPrint;
    siblings: number | null;
    birth_order: number | null;
    school_siblings: number | null;
    family_ill: string;
    household: string;
    notes: string;
}

defineProps<{
    forms: FormPrint[];
    school: { name: string };
}>();

function today(): string {
    return new Date().toLocaleDateString('tr-TR');
}
</script>

<template>
    <PrintLayout title="Öğrenci Bilgi Formu" back-href="/bilgi-formlari">
        <div class="no-print mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">
            Yazdırırken: Kenar boşlukları <strong>Yok</strong>, Ölçek <strong>%100</strong>, <strong>Arka plan grafikleri açık</strong> olmalı.
        </div>

        <div
            v-for="(f, i) in forms"
            :key="i"
            class="bilgi-form mx-auto mb-8 max-w-[210mm] break-after-page bg-white text-[11px] leading-snug text-gray-900 last:mb-0"
        >
            <div class="flex items-center justify-between border-b-2 border-gray-400 pb-2">
                <div class="text-lg font-bold uppercase tracking-wide text-gray-800">Öğrenci Bilgi Formu</div>
                <div class="text-xs text-gray-600">Tarih: {{ today() }}</div>
            </div>

            <div class="bf-head mt-3 px-2 py-1 text-center text-sm font-bold uppercase">Öğrenci Bilgisi</div>
            <table class="bf-table">
                <tbody>
                    <tr><td class="w-1/2"><strong>Adın Soyadın:</strong> {{ f.full_name }}</td><td><strong>Cinsiyetin:</strong> {{ f.gender }}</td></tr>
                    <tr><td><strong>Sınıfın ve Numaran:</strong> {{ f.branch }} / {{ f.number }}</td><td><strong>Doğum Yeri ve Tarihi:</strong> {{ f.birth }}</td></tr>
                    <tr><td><strong>Okulun:</strong> {{ school.name }}</td><td><strong>Adresin:</strong> {{ f.address }}</td></tr>
                    <tr><td><strong>Okul öncesi eğitim aldın mı?</strong> {{ f.preschool }}</td><td><strong>Sürekli kullandığın ilaç ve tıbbi cihaz var mı? Nedir?</strong> {{ f.medication }}</td></tr>
                    <tr><td><strong>Ne yapmaktan hoşlanırsın?</strong> {{ f.hobbies }}</td><td><strong>Sürekli bir hastalığın var mı? Varsa nedir?</strong> {{ f.illness }}</td></tr>
                    <tr><td><strong>Yakın zamanda taşındın mı, okul değiştirdin mi?</strong> {{ f.moved }}</td><td><strong>Ders dışı faaliyetlerin nelerdir?</strong> {{ f.extracurricular }}</td></tr>
                    <tr><td><strong>Kendine ait teknolojik aletlerin var mı?</strong> {{ f.tech }}</td><td><strong>Hâlâ etkisi altında olduğun bir olay yaşadın mı?</strong> {{ f.trauma }}</td></tr>
                </tbody>
            </table>

            <div class="bf-head mt-3 px-2 py-1 text-center text-sm font-bold uppercase">Veli Bilgisi</div>
            <table class="bf-table">
                <tbody>
                    <tr><td class="w-1/2"><strong>Adı-Soyadı:</strong> {{ f.guardian.name }}</td><td><strong>Yakınlığı:</strong> {{ f.guardian.relation }}</td></tr>
                    <tr><td><strong>Telefon Numarası:</strong> {{ f.guardian.phone }}</td><td></td></tr>
                    <tr><td><strong>Eğitim Durumu:</strong> {{ f.guardian.education }}</td><td><strong>Mesleği:</strong> {{ f.guardian.job }}</td></tr>
                </tbody>
            </table>

            <table class="bf-table mt-3">
                <tbody>
                    <tr class="bf-head text-center font-bold"><td class="w-[38%]">Anne</td><td></td><td class="w-[38%]">Baba</td></tr>
                    <tr><td>{{ f.mother.name }}</td><td class="text-center font-semibold">Adı Soyadı</td><td>{{ f.father.name }}</td></tr>
                    <tr><td>{{ f.mother.birth }}</td><td class="text-center font-semibold">Doğum Yeri / Doğum Tarihi</td><td>{{ f.father.birth }}</td></tr>
                    <tr><td>{{ f.mother.biological }}</td><td class="text-center font-semibold">Öz mü?</td><td>{{ f.father.biological }}</td></tr>
                    <tr><td>{{ f.mother.alive }}</td><td class="text-center font-semibold">Sağ mı?</td><td>{{ f.father.alive }}</td></tr>
                    <tr><td>{{ f.mother.disability }}</td><td class="text-center font-semibold">Engel durumu var mı?</td><td>{{ f.father.disability }}</td></tr>
                    <tr><td>{{ f.mother.education }}</td><td class="text-center font-semibold">Eğitim Durumu</td><td>{{ f.father.education }}</td></tr>
                    <tr><td>{{ f.mother.job }}</td><td class="text-center font-semibold">Mesleği</td><td>{{ f.father.job }}</td></tr>
                </tbody>
            </table>

            <div class="bf-head mt-3 px-2 py-1 text-center text-sm font-bold uppercase">Aile Bilgisi</div>
            <table class="bf-table">
                <tbody>
                    <tr><td class="w-1/2"><strong>Kaç kardeşsin?</strong> {{ f.siblings ?? '' }}</td><td><strong>Ailenin kaçıncı çocuğusun?</strong> {{ f.birth_order ?? '' }}</td></tr>
                    <tr><td><strong>Okula giden kardeş sayın:</strong> {{ f.school_siblings ?? '' }}</td><td><strong>Aile üyelerinde sürekli hastalık/engel olan var mı?</strong> {{ f.family_ill }}</td></tr>
                    <tr><td colspan="2"><strong>Evinizde sizinle birlikte kim/kimler yaşıyor?</strong> {{ f.household }}</td></tr>
                    <tr v-if="f.notes"><td colspan="2"><strong>Notlar:</strong> {{ f.notes }}</td></tr>
                </tbody>
            </table>

            <div class="mt-2 text-right text-xs font-semibold">TEŞEKKÜR EDERİZ</div>
        </div>
    </PrintLayout>
</template>

<style>
.bilgi-form {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
.bf-head {
    background-color: #c9d1a3;
}
.bf-table {
    width: 100%;
    border-collapse: collapse;
}
.bf-table td {
    border: 1px solid #9aa07a;
    padding: 3px 6px;
    vertical-align: top;
}
@media print {
    .bilgi-form {
        break-after: page;
    }
}
</style>
