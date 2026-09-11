export type RoleName = 'admin' | 'staff_input' | 'viewer';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    roles: string[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

export type EmployeeStatus = 'aktif' | 'non_aktif';

export interface EmployeeRow {
    id: number;
    nama: string;
    nik: string;
    no_rekening: string;
    status: EmployeeStatus;
}

export interface EmployeeDetail {
    id: number;
    nama: string;
    nik: string;
    alamat: string;
    no_rekening: string;
    nama_bank: string | null;
    status: EmployeeStatus;
}
