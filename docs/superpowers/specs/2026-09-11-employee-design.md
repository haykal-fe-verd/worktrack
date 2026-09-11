# Data Karyawan (Employee) — Fase 1 Sub-Project 2

Status: Disetujui untuk implementasi
Tanggal: 11 September 2026
Induk spec: [2026-09-09-worktrack-mvp-design.md](2026-09-09-worktrack-mvp-design.md) §6.3 (Employee), §8.1 (Data Karyawan), K2 (akses NIK/rekening)
Bergantung pada: [2026-09-10-auth-roles-design.md](2026-09-10-auth-roles-design.md) (role admin/staff_input/viewer sudah ada)

## 0. Keputusan dari Klarifikasi

| # | Topik | Keputusan |
|---|---|---|
| E1 | Scope Import/Export Excel | Disertakan di sub-project ini (bukan ditunda ke sub-project #6), karena keduanya bagian tak terpisahkan dari siklus hidup data Karyawan |
| E2 | Akses CRUD | Admin & Staff Input bisa create/edit/toggle status; Viewer read-only (masked) |
| E3 | Format masking | 4 karakter depan + bintang + 4 karakter belakang, berlaku sama untuk NIK & No. Rekening, untuk panjang apa pun (lihat §3.2 untuk aturan panjang pendek) |
| E4 | Status sebagai pengganti hapus | Tidak ada hapus permanen di UI — hanya toggle `aktif`/`non_aktif`, sesuai Asumsi A8 di PRD induk. Admin & Staff Input berdua bisa toggle. |
| E5 | Alur review import | Baris valid langsung tersimpan; baris bermasalah dikumpulkan jadi laporan error Excel yang bisa didownload — bukan antrian "pending review" di database dengan UI approve/reject terpisah (YAGNI untuk Fase 1) |
| E6 | Pagination list | Ya, 20 baris/halaman + search (nama/NIK) + filter status |

## 1. Lingkup

**Masuk:**
- CRUD Employee (create, update, toggle status) dengan validasi NIK unik & format
- List dengan pagination, search, filter status
- Masking NIK & No. Rekening untuk role Viewer (list, detail, export)
- Import Excel (format Image 1 pada PRD: NAMA, NIK, ALAMAT, NO REKENING) dengan laporan error untuk baris bermasalah
- Export Excel (Admin & Staff Input saja)

**Tidak masuk (di luar scope sub-project ini):**
- Hapus permanen dari UI (hanya lewat akses database langsung, sesuai Aturan Bisnis #5 PRD induk)
- Relasi ke Job/Assignment — itu sub-project #3 dan #4 berikutnya, Employee di sini berdiri sendiri
- Antrian review manual di database dengan UI approve/reject (YAGNI, lihat E5)
- Riwayat perubahan field Employee (nama, alamat, dst berubah) — PRD induk tidak mensyaratkan versioning untuk data karyawan itu sendiri (berbeda dari Assignment yang wajib versioning), cukup `updated_at` standar

## 2. Model Data

**employees**

| Field | Tipe | Catatan |
|---|---|---|
| id | bigint, PK | |
| nik | string(16), unique | Wajib string (Asumsi A4), tepat 16 digit angka |
| nama | string(255) | |
| alamat | text | |
| no_rekening | string(50) | Wajib string (Asumsi A4), leading zero aman |
| nama_bank | string(100), nullable | Tidak ada di Excel sumber, field tambahan sesuai saran PRD |
| status | enum('aktif','non_aktif') | Default `aktif` |
| created_at, updated_at | timestamp | |

Tidak ada `deleted_at` (soft delete Laravel) — status `non_aktif` sudah berfungsi sebagai penanda "tidak aktif", dan PRD induk secara eksplisit ingin flag status, bukan mekanisme soft-delete generik.

## 3. Aturan Bisnis

### 3.1 Validasi

- `nik`: wajib, string, **tepat 16 karakter**, hanya digit (regex `^\d{16}$`), unique di tabel `employees` (kecuali saat update, unique ignore diri sendiri).
- `nama`, `alamat`, `no_rekening`: wajib, string.
- `no_rekening`: hanya digit (regex `^\d+$`), tidak ada batas panjang tetap (bervariasi per bank).
- `nama_bank`: opsional.
- `status`: hanya bisa diubah lewat aksi toggle khusus (endpoint terpisah dari update field lain), bukan field bebas di form edit biasa — mencegah perubahan status tak sengaja saat edit data lain.

### 3.2 Masking

Fungsi masking berlaku untuk `nik` dan `no_rekening` saat ditampilkan ke role `viewer`:
- Jika panjang string > 8 karakter: 4 karakter pertama + `******` (selalu 6 bintang, bukan menyesuaikan sisa panjang — supaya panjang asli tidak bisa ditebak dari jumlah bintang) + 4 karakter terakhir. Contoh: `3513126804000001` → `3513******0001`.
- Jika panjang string ≤ 8 karakter: seluruh karakter diganti `******` (6 bintang tetap), tidak ada karakter asli yang ditampilkan — mencegah kebocoran nomor pendek yang hampir seluruhnya akan terlihat kalau memakai pola 4+4.

Masking diterapkan di **response backend** (Controller/Resource), bukan di frontend — supaya Viewer tidak pernah menerima data asli lewat network request sama sekali (frontend-only masking tidak aman, hanya UI, sesuai catatan spec induk §3.6).

### 3.3 Toggle Status

- Endpoint terpisah `PATCH /employees/{employee}/toggle-status` — membalik `aktif` ↔ `non_aktif`. Tidak ada input body.
- Bisa diakses Admin & Staff Input.
- Tidak ada validasi "tidak bisa nonaktifkan kalau masih ada Assignment aktif" — itu belum relevan karena Assignment belum ada di Fase ini; jadi belum perlu guard tambahan (YAGNI, dicatat sebagai potensi hardening saat sub-project Assignment dibangun).

### 3.4 Import

1. User upload file `.xlsx`/`.xls` lewat form Import (Admin & Staff Input saja).
2. Sistem membaca baris dengan header `NAMA`, `NIK`, `ALAMAT`, `NO REKENING` (heading row, case-insensitive, PhpSpreadsheet/maatwebsite konversi ke snake_case: `nama`, `nik`, `alamat`, `no_rekening`).
3. Per baris:
   - Jika `nik` tidak match `^\d{16}$`, atau `nama`/`alamat`/`no_rekening` kosong → baris **gagal**, masuk daftar error dengan alasan spesifik ("NIK harus 16 digit angka", "Nama wajib diisi", dst).
   - Jika `nik` sudah ada di database:
     - Dan `nama` di database **sama persis** dengan baris → baris **dilewati** (sudah ada, bukan error, dihitung terpisah sebagai "dilewati").
     - Dan `nama` di database **berbeda** → baris **gagal**, alasan "NIK sudah terdaftar atas nama lain: {nama lama}".
   - Jika `nik` valid dan belum ada di database → baris **berhasil disimpan** sebagai Employee baru dengan status `aktif`, `nama_bank` kosong (tidak ada di sumber Excel).
4. Setelah semua baris diproses, sistem menampilkan ringkasan: jumlah berhasil, dilewati, gagal. Kalau ada yang gagal, sediakan tombol download laporan error — file Excel berisi kolom asli (NAMA, NIK, ALAMAT, NO REKENING) + kolom tambahan `Alasan Gagal`.
5. Laporan error digenerate saat itu juga dari data di memori (bukan disimpan permanen di storage/database) dan langsung didownload sebagai response — tidak perlu tabel `import_errors` atau job queue untuk Fase ini (volume kecil, sesuai skala PRD §9 "ratusan karyawan").

### 3.5 Export

- Tombol Export di halaman list, hanya untuk Admin & Staff Input.
- Export **semua** data Employee (tidak dibatasi oleh filter/search yang sedang aktif di UI — Fase 1 sederhana, export-all cukup untuk skala data yang ada).
- Kolom: Nama, NIK, Alamat, No. Rekening, Nama Bank, Status. Karena hanya Admin/Staff Input yang bisa export, data yang diexport **selalu versi penuh (tidak di-mask)** — tidak ada mekanisme export untuk Viewer sama sekali.

## 4. Fitur / Modul

- **Index** (`/employees`): tabel paginated (20/halaman), search box (nama atau NIK), dropdown filter status, tombol "Tambah Karyawan" + "Import" + "Export" (3 tombol terakhir disembunyikan untuk Viewer). Kolom aksi: Edit, toggle Aktifkan/Nonaktifkan (disembunyikan untuk Viewer).
- **Create** (`/employees/create`): form nama, NIK, alamat, no. rekening, nama bank (opsional). Status otomatis `aktif`, tidak ada di form.
- **Edit** (`/employees/{employee}/edit`): form sama seperti Create (tanpa field status — status diubah lewat toggle di halaman Index, bukan di form ini, sesuai §3.1).
- **Import** (`/employees/import`): form upload file, setelah submit tampilkan ringkasan hasil + link download laporan error jika ada.
- **Export**: aksi langsung (bukan halaman terpisah) dari tombol di Index, response berupa file download.

## 5. Testing

- Validasi: NIK harus 16 digit, unique (create & update), field wajib lainnya.
- Otorisasi: Viewer diblokir (403) dari create/update/toggle-status/import/export; Admin & Staff Input bisa semua.
- Masking: response Index/show untuk Viewer menampilkan NIK & rekening ter-mask; response untuk Admin/Staff Input menampilkan versi penuh.
- Import: baris valid tersimpan; baris NIK duplikat-nama-sama dilewati (tidak dobel); baris NIK duplikat-nama-beda masuk laporan error; baris format NIK salah masuk laporan error; ringkasan (berhasil/dilewati/gagal) akurat.
- Export: response adalah file Excel, berisi data tidak ter-mask, hanya bisa diakses Admin/Staff Input.
- Toggle status: aktif → non_aktif → aktif berjalan benar, tidak ada endpoint lain yang bisa mengubah status.

## 6. Arsitektur Teknis

Tidak ada perubahan dari spec induk §8/§10. Menggunakan `maatwebsite/excel` yang sudah terpasang (termasuk ekstensi `gd` yang sudah di-set up di image Docker). Tidak menambah package baru.
