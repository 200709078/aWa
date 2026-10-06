export interface TeacherFormData {
    first_name: string;
    last_name: string;
    phone: string;
    email: string;
    address: string;
    duty: string;
    branch: string;
    started_at: string;
    photo: File | null;
    errors: Partial<Record<string, string>>;
}
