export type RoleName = 'admin' | 'staff_input' | 'viewer';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    roles: string[];
}

export interface Flash {
    success: string | null;
    error: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash: Flash;
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
    from: number | null;
    to: number | null;
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

export type JobStatus = 'aktif' | 'selesai';
export type JobPeriodStatus = 'aktif' | 'berakhir' | 'diperbarui';
export type DocumentType = 'PR' | 'PO' | 'DO' | 'WO';

export interface JobRow {
    id: number;
    nama_pekerjaan: string;
    klien: string | null;
    lokasi: string | null;
    status: JobStatus;
    periods_count: number;
}

export interface JobDetail {
    id: number;
    nama_pekerjaan: string;
    lokasi: string | null;
    klien: string | null;
    status: JobStatus;
}

export interface JobPeriodRow {
    id: number;
    jenis_dokumen: DocumentType;
    no_dokumen: string;
    kode_po: string | null;
    nilai_po: number;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    jumlah_tk_rencana: number;
    status: JobPeriodStatus;
}

export type AssignmentStatus = 'aktif' | 'selesai' | 'diperbarui';

export interface AssignmentRow {
    id: number;
    employee_nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    status: AssignmentStatus;
    is_current: boolean;
    tarif_jual: string | null;
    tarif_bayar: string | null;
}

export interface JobPeriodDetail {
    id: number;
    job_id: number;
    job_nama_pekerjaan: string;
    jenis_dokumen: DocumentType;
    no_dokumen: string;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    jumlah_tk_rencana: number;
    status: JobPeriodStatus;
}

export interface EmployeeAssignmentRow {
    id: number;
    job_nama_pekerjaan: string;
    no_dokumen: string;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    status: AssignmentStatus;
}

export type AttendanceStatusValue = 'hadir' | 'tidak_hadir' | 'izin';

export interface AttendanceCellData {
    tanggal: string;
    disabled: boolean;
    status: AttendanceStatusValue | null;
    catatan: string | null;
}

export interface AttendanceGridRow {
    assignment_id: number;
    employee_nama: string;
    cells: AttendanceCellData[];
}

export interface JobOption {
    id: number;
    nama_pekerjaan: string;
}

export type AttendanceRecapStatusValue =
    | AttendanceStatusValue
    | 'belum_diisi';

export interface AttendanceRecapRow {
    assignment_id: number;
    employee_nama: string;
    job_nama_pekerjaan: string;
    no_dokumen: string;
    days: Record<string, AttendanceRecapStatusValue>;
    summary: { hadir: number; tidak_hadir: number; izin: number };
}

export interface AttendanceFilters {
    date_from: string;
    date_to: string;
    job_id: number | null;
}
