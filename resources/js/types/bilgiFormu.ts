export interface GuardianBlock {
    name: string;
    phone: string;
    birth_place: string;
    birth_date: string;
    education: string;
    occupation: string;
    biological: boolean | null;
    alive: boolean | null;
    disability: string;
    illness: string;
}

export function blankGuardianBlock(): GuardianBlock {
    return {
        name: '',
        phone: '',
        birth_place: '',
        birth_date: '',
        education: '',
        occupation: '',
        biological: null,
        alive: null,
        disability: '',
        illness: '',
    };
}

export const relationOptions = [
    { value: 'anne', label: 'Anne' },
    { value: 'baba', label: 'Baba' },
    { value: 'veli', label: 'Veli' },
    { value: 'vasi', label: 'Vasi' },
];
