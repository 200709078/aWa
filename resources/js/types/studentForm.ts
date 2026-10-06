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
    photo: File | null;
    is_active: boolean;
    guardians: GuardianForm[];
    errors: Partial<Record<string, string>>;
}

export function blankGuardian(relation = 'veli', is_primary = false): GuardianForm {
    return { id: null, relation, first_name: '', last_name: '', phone: '', is_primary };
}

export const relationOptions = [
    { value: 'anne', label: 'Anne' },
    { value: 'baba', label: 'Baba' },
    { value: 'veli', label: 'Veli' },
    { value: 'vasi', label: 'Vasi' },
];
