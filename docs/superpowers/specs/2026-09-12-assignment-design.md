# Assignment & Riwayat Versi Pekerja — Fase 1 Sub-Project 4

Status: Disetujui untuk implementasi
Tanggal: 12 September 2026
Induk spec: [2026-09-09-worktrack-mvp-design.md](2026-09-09-worktrack-mvp-design.md) §1.1 (Assignment), §2 (Aturan Bisnis Tambahan); [PRD_Sistem_Manajemen_Pekerja_Pekerjaan_PR.md](../PRD_Sistem_Manajemen_Pekerja_Pekerjaan_PR.md) §6.3 (Assignment), §7 (Aturan Bisnis #1-#3), §8.3
Bergantung pada: [2026-09-10-auth-roles-design.md](2026-09-10-auth-roles-design.md) (role), [2026-09-11-employee-design.md](2026-09-11-employee-design.md) (Employee, pola masking), [2026-09-12-job-period-design.md](2026-09-12-job-period-design.md) (Job, JobPeriod, pola CRUD)

## 0. Keputusan dari Klarifikasi

| # | Topik | Keputusan |
|---|---|---|
| A1 | Aturan Bisnis #3 (peringatan jumlah TK) | Masuk scope sekarang — dulunya ditunda (J4) sampai modul Assignment ada |
| A2 | Skenario (c) Job "Perbarui PR" | Diubah dari pesan info menjadi redirect ke halaman kelola Assignment untuk JobPeriod aktif |
| A3 | Struktur halaman utama | Per-JobPeriod: halaman detail JobPeriod menampilkan daftar Assignment + tombol Assign, bukan modul Assignment terpisah |
| A4 | Riwayat per karyawan | Section baru di halaman detail Employee (bukan halaman terpisah) |
| A5 | Field tarif_jual/tarif_bayar | Disertakan sekarang, nullable, tanpa validasi/kalkulasi margin, masking sama seperti NIK untuk Viewer |
| A6 | Multi-assignment | Satu Employee boleh punya banyak Assignment aktif bersamaan di JobPeriod berbeda |
| A7 | Trigger versioning | Hanya perubahan tanggal (mulai/selesai) yang memicu versi baru; perubahan tarif update langsung tanpa versioning |
| A8 | Mengakhiri Assignment | Tombol "Akhiri Penugasan" — set tanggal_selesai & status=selesai pada baris current, is_current tetap true (tidak ada versi baru karena tidak ada penerus) |
| A9 | Import Excel Assignment | Ditunda — sub-project ini CRUD manual saja |

## 1. Lingkup

**Masuk:**
- CRUD Assignment (assign karyawan ke JobPeriod, tanggal mulai/selesai, tarif_jual/tarif_bayar opsional)
- Mekanisme versioning append-only saat tanggal assignment berubah (tidak overwrite, riwayat lengkap)
- Aksi "Akhiri Penugasan" untuk assignment yang berhenti tanpa penerus
- Peringatan (bukan blokir) jika jumlah assignment aktif pada JobPeriod ≠ `jumlah_tk_rencana`
- Riwayat Assignment per JobPeriod (di halaman detail JobPeriod) dan per Employee (section baru di halaman detail Employee)
- Perubahan skenario (c) di modul Job: dari pesan info menjadi redirect ke halaman kelola Assignment
- Masking `tarif_jual`/`tarif_bayar` untuk role Viewer (pola sama seperti NIK/rekening)
- Dashboard: card "Assignment" diisi count assignment aktif nyata

**Tidak masuk (di luar scope sub-project ini):**
- Import Excel Assignment — CRUD manual dulu, import menyusul di sub-project migrasi terpisah (A9)
- Modul Attendance/Rekap Mingguan — sub-project berikutnya
- Validasi/kalkulasi margin dari tarif_jual/tarif_bayar — sekadar disimpan (sesuai spec induk §2 poin 8)
- ChangeLog audit generik — di luar scope Fase 1 versioning bisnis ini (field `previous_assignment_id` sudah cukup untuk riwayat bisnis)

## 2. Model Data

**assignments**

| Field | Tipe | Catatan |
|---|---|---|
| id | bigint, PK | |
| employee_id | bigint, FK → employees | |
| job_period_id | bigint, FK → job_periods | |
| tanggal_mulai | date | |
| tanggal_selesai | date, nullable | |
| status | string(20) | enum `aktif`/`selesai`/`diperbarui`, default `aktif` |
| is_current | boolean | default `true` — menandai versi aktif dari rantai riwayat assignment ini |
| previous_assignment_id | bigint, FK → assignments, nullable | Diisi hanya saat versi baru dibuat akibat perubahan tanggal (A7) |
| tarif_jual | decimal(12,2), nullable | Harga jasa ditagih ke klien per pekerja; masked untuk Viewer (A5) |
| tarif_bayar | decimal(12,2), nullable | Harga dibayar ke pekerja; masked untuk Viewer (A5) |
| created_by | bigint, FK → users | |
| created_at, updated_at | timestamp | |

Index: `(job_period_id, is_current)`, `(employee_id, is_current)` — untuk query cepat "siapa aktif sekarang" per JobPeriod/Employee.

Enum baru: `AssignmentStatus` (Aktif, Selesai, Diperbarui) — pola sama seperti `JobPeriodStatus`.

## 3. Aturan Bisnis

### 3.1 Validasi

- `employee_id`, `job_period_id`, `tanggal_mulai`: wajib.
- `employee_id` yang dipilih harus berstatus `aktif` (Employee) — dropdown assign hanya menampilkan Employee aktif.
- `job_period_id` yang dipilih harus berstatus `aktif` (JobPeriod) — hanya JobPeriod aktif yang bisa menerima assignment baru.
- `tanggal_selesai`: nullable, kalau diisi harus `>= tanggal_mulai`.
- Tidak boleh duplikat: Employee yang sama tidak boleh punya lebih dari satu Assignment dengan `is_current = true` pada `job_period_id` yang sama (harus edit/akhiri assignment yang ada dulu).
- Employee yang sama BOLEH punya banyak Assignment `is_current = true` di `job_period_id` yang berbeda (A6 — multi-assignment).
- `status` dan `is_current`: hanya bisa diubah lewat aksi versioning/akhiri (bukan field bebas di form edit biasa).

### 3.2 Membuat Assignment Baru

Form "Assign Karyawan" di halaman detail JobPeriod: pilih Employee, isi tanggal_mulai, tanggal_selesai (opsional), tarif_jual (opsional), tarif_bayar (opsional). Assignment baru: `status = aktif`, `is_current = true`, `previous_assignment_id = null`, `created_by` = user yang membuat.

### 3.3 Mekanisme Versi (Aturan Bisnis #1 — tidak overwrite)

Saat staf mengedit `tanggal_mulai` atau `tanggal_selesai` Assignment yang `is_current = true`:
1. Baris lama: `status = diperbarui`, `is_current = false` (tetap tersimpan sebagai riwayat, read-only).
2. Baris baru dibuat: field sama seperti baris lama kecuali tanggal yang diubah, `previous_assignment_id` = id baris lama, `is_current = true`, `status = aktif`.

Perubahan `tarif_jual`/`tarif_bayar` **tidak** memicu versioning — diupdate langsung di baris `is_current = true` yang berjalan (A7).

Operasi ini dibungkus `DB::transaction()` (pola sama seperti modul Job Task 6 fix wave).

### 3.4 Akhiri Penugasan (Aturan Bisnis #1, kasus tanpa penerus)

Tombol "Akhiri Penugasan" pada baris Assignment `is_current = true`: staf pilih tanggal akhir → sistem set `tanggal_selesai` = tanggal itu, `status = selesai` pada baris yang sama, `is_current` **tetap true** (baris final, tidak ada `previous_assignment_id` baru karena tidak ada penerus). Beda dengan §3.3: di sini tidak ada baris baru dibuat.

### 3.5 Peringatan Jumlah TK (Aturan Bisnis #3)

Di halaman detail JobPeriod: hitung jumlah Assignment dengan `is_current = true AND status = aktif` untuk JobPeriod itu, bandingkan dengan `jumlah_tk_rencana`. Jika tidak sama → tampilkan badge/alert peringatan (mis. "Jumlah TK: 3 dari rencana 5"), tidak memblokir aksi apa pun (assign/akhiri tetap bisa dilakukan).

### 3.6 Skenario (c) Job "Perbarui PR" (revisi dari modul Job)

Sebelumnya (spec Job J3): skenario (c) menampilkan pesan info statis tanpa aksi, karena modul Assignment belum ada. Sekarang: skenario (c) redirect langsung ke halaman detail JobPeriod aktif Job tersebut (halaman yang sama dengan §3.5/§4 di bawah), di mana staf bisa assign/edit/akhiri Assignment. Tidak ada JobPeriod baru dibuat, sesuai Aturan Bisnis #2(c) PRD.

### 3.7 Riwayat

- **Per JobPeriod**: halaman detail JobPeriod menampilkan tabel semua Assignment yang pernah terhubung (current + `diperbarui` + `selesai`), diurutkan terbaru dulu, dengan info Employee, tanggal, status.
- **Per Employee**: section baru "Riwayat Penugasan" di halaman detail Employee, menampilkan semua Assignment employee itu (current + histori) dengan info Job/JobPeriod (nama_pekerjaan, no_dokumen), tanggal, status.

## 4. Fitur / Modul

- **Detail JobPeriod** (halaman baru `job-periods.show`, diakses dari `jobs.show`): info JobPeriod, badge peringatan jumlah TK (§3.5), tabel Assignment (Employee, tanggal mulai/selesai, status, tarif — masked untuk Viewer), tombol "Assign Karyawan" (Admin/Staff Input saja).
- **Assign Karyawan** (form, Admin/Staff Input saja): dropdown Employee (aktif saja), tanggal_mulai, tanggal_selesai, tarif_jual, tarif_bayar.
- **Edit Assignment** (Admin/Staff Input saja): form edit tanggal/tarif — backend menentukan otomatis apakah trigger versioning (tanggal berubah) atau update langsung (cuma tarif).
- **Akhiri Penugasan** (aksi, Admin/Staff Input saja): konfirmasi + input tanggal akhir → set selesai (§3.4).
- **Riwayat per Employee**: section di `employees.show` (semua role bisa lihat, tarif masked untuk Viewer).
- **Skenario (c) Job**: redirect ke Detail JobPeriod aktif (§3.6) — perubahan pada `JobController::renew`/halaman pilih skenario yang sudah ada.
- **Dashboard**: card "Assignment" diisi count Assignment `is_current=true AND status=aktif`.

## 5. Testing

- Validasi: field wajib, Employee/JobPeriod harus aktif saat assign baru, `tanggal_selesai >= tanggal_mulai`, duplikat Employee+JobPeriod `is_current` ditolak.
- Multi-assignment: Employee sama bisa `is_current=true` di JobPeriod berbeda.
- Versioning: edit tanggal → baris lama `diperbarui`/`is_current=false`, baris baru `previous_assignment_id` benar, `is_current=true`.
- Edit tarif saja → tidak membuat baris baru, tidak mengubah `is_current`/`status`.
- Akhiri Penugasan → `status=selesai`, `is_current` tetap true, tidak ada baris baru.
- Peringatan jumlah TK: aktif count ≠ `jumlah_tk_rencana` → warning tampil di halaman; sama → tidak tampil.
- Otorisasi: Viewer diblokir dari semua aksi tulis (assign/edit/akhiri); tarif_jual/tarif_bayar masked untuk Viewer di semua tampilan (JobPeriod detail, Employee riwayat); Admin & Staff Input bisa semua aksi & lihat tarif penuh.
- Riwayat: halaman JobPeriod menampilkan semua Assignment historis terkait; halaman Employee menampilkan semua Assignment historis milik employee itu.
- Skenario (c) Job: redirect ke halaman Detail JobPeriod yang benar (JobPeriod aktif Job tsb), tidak membuat JobPeriod baru.
- Dashboard: count Assignment aktif nyata.

## 6. Arsitektur Teknis

Tidak ada perubahan dari spec induk §8/§10, tidak ada package baru. Mengikuti pola yang sudah dibangun di modul Employee & Job: `#[Fillable]` + `casts()` di model, `role:admin|staff_input` middleware untuk aksi tulis, `App\Support\Masks::partial()` untuk `tarif_jual`/`tarif_bayar` di tampilan Viewer (sama seperti NIK/rekening), `DB::transaction()` untuk operasi multi-write (versioning, akhiri penugasan).
