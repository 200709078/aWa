export interface EduRow {
    id: number | null;
    city: string;
    institution_name: string;
    faculty: string;
    department: string;
}

export interface GraduateFormData {
    graduation_year: number;
    graduation_number: string;
    first_name: string;
    last_name: string;
    phone: string;
    email: string;
    educations: EduRow[];
    company: string;
    job_city: string;
    photo: File | null;
    errors: Partial<Record<string, string>>;
}

export function blankEduRow(): EduRow {
    return { id: null, city: '', institution_name: '', faculty: '', department: '' };
}
