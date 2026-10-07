export interface GuardianForm {
    id: number | null;
    relation: string;
    first_name: string;
    last_name: string;
    phone: string;
    is_primary: boolean;
}

export interface StudentFormData {
    branch_id: number | null;
    school_number: string;
    first_name: string;
    last_name: string;
    phone: string;
    email: string;
    address: string;
    gender: string | null;
    birth_place: string;
    birth_date: string;
    blood_type: string | null;
    religion: string;
    height_cm: number | null;
    weight_kg: number | null;
    photo: File | null;
    is_active: boolean;
    guardians: GuardianForm[];
    errors: Partial<Record<string, string>>;
}

export const genderOptions = [
    { value: 'Kız', label: 'Kız' },
    { value: 'Erkek', label: 'Erkek' },
];

export const bloodTypeOptions = [
    { value: 'A Rh+', label: 'A Rh+' },
    { value: 'A Rh-', label: 'A Rh-' },
    { value: 'B Rh+', label: 'B Rh+' },
    { value: 'B Rh-', label: 'B Rh-' },
    { value: 'AB Rh+', label: 'AB Rh+' },
    { value: 'AB Rh-', label: 'AB Rh-' },
    { value: '0 Rh+', label: '0 Rh+' },
    { value: '0 Rh-', label: '0 Rh-' },
];

export function blankGuardian(relation = 'veli', is_primary = false): GuardianForm {
    return { id: null, relation, first_name: '', last_name: '', phone: '', is_primary };
}

export const relationOptions = [
    { value: 'anne', label: 'Anne' },
    { value: 'baba', label: 'Baba' },
    { value: 'veli', label: 'Diğer' },
];
