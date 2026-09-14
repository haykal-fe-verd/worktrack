# Rekap Absensi Mingguan — Fase 1 Sub-Project 6

Status: Disetujui untuk implementasi
Tanggal: 14 September 2026
Induk spec: [2026-09-09-worktrack-mvp-design.md](2026-09-09-worktrack-mvp-design.md) §1.2 (Attendance), §2 (Aturan Bisnis Tambahan #6-#8), §3 (Modul — Rekap Absensi Mingguan)
Bergantung pada: [2026-09-10-auth-roles-design.md](2026-09-10-auth-roles-design.md) (role), [2026-09-12-assignment-design.md](2026-09-12-assignment-design.md) (Assignment, JobPeriod), pola shadcn/ui yang sudah dibangun di seluruh migrasi UI (sub-project 1-5, selesai)

## 0. Keputusan dari Klarifikasi

| # | Topik | Keputusan |
|---|---|---|
| A1 | Rentang tanggal Input Absensi | Minggu berjalan (Senin-Minggu) dengan navigasi ‹ › maju/mundur, bukan date picker bebas |
| A2 | Interaksi grid | Klik sel → popover kecil pilih status (Hadir/Tidak Hadir/Izin) + catatan opsional, auto-save per sel |
| A3 | Alur pemilihan | Pilih Job saja → sistem otomatis pakai JobPeriod berstatus aktif milik Job itu |
| A4 | Sel di luar rentang assignment | Abu-abu/disabled, tidak bisa diklik (termasuk sebelum assignment mulai, sesudah berakhir, atau tanggal di masa depan) |
| A5 | Filter Rekap Mingguan | Rentang tanggal (wajib) + Job (opsional, default semua Job) |
| A6 | Akses Export Rekap | Semua role (bukan cuma Admin/Staff Input) — laporan ini tidak berisi data sensitif (NIK/tarif) |
| A7 | Pola UI | Halaman penuh (bukan Dialog/Sheet) — alat grid/laporan interaktif, bukan alur CRUD satu record |
| A8 | Dashboard card "Absensi Mingguan" | Diisi jumlah sel "belum diisi" pada minggu berjalan (lebih actionable daripada total record) |

## 1. Lingkup

**Masuk:**
- Model `Attendance` dengan validasi rentang tanggal & status assignment (Aturan Bisnis #6)
- Sub-modul Input Absensi: pilih Job → grid karyawan × 7 hari minggu berjalan → klik sel untuk isi status
- Sub-modul Rekap Mingguan: filter tanggal + Job opsional → tabel rekap dengan penanda "belum diisi" (Aturan Bisnis #7) → export Excel
- Dashboard: card "Absensi Mingguan" diisi count nyata

**Tidak masuk (di luar scope sub-project ini):**
- Import Excel data absensi historis — spec induk §4 menegaskan Attendance tidak dimigrasi dari Excel lama (tidak ada data historis granular harian)
- Kalkulasi margin/finansial dari tarif_jual/tarif_bayar terhadap absensi — tetap di luar scope Fase 1
- ChangeLog audit generik untuk Attendance — mengikuti pola Assignment (riwayat bisnis cukup lewat data Attendance itu sendiri, bukan lewat ChangeLog)

## 2. Model Data

**attendances**

| Field | Tipe | Catatan |
|---|---|---|
| id | bigint, PK | |
| assignment_id | bigint, FK → assignments | Menentukan employee + job_period secara implisit |
| tanggal | date | |
| status | string(20) | enum `hadir`/`tidak_hadir`/`izin` |
| catatan | text, nullable | |
| recorded_by | bigint, FK → users | |
| created_at, updated_at | timestamp | |

Constraint: unique `(assignment_id, tanggal)` di level DB — satu status per hari per assignment. Divalidasi juga di aplikasi (bukan hanya DB constraint).

Enum baru: `AttendanceStatus` (Hadir, TidakHadir, Izin) — pola sama seperti `AssignmentStatus`/`JobPeriodStatus`.

## 3. Aturan Bisnis

### 3.1 Validasi Input Absensi

- `tanggal` harus berada dalam rentang `tanggal_mulai`–`tanggal_selesai` milik assignment terkait (kalau `tanggal_selesai` null, boleh sampai hari ini) — divalidasi di aplikasi, bukan hanya constraint DB.
- Assignment yang dipilih harus berstatus `aktif` atau `diperbarui` — tidak bisa input attendance untuk assignment yang sudah `selesai` sepenuhnya di luar rentang tanggalnya, dan tidak bisa untuk assignment yang `tanggal_mulai`-nya di masa depan (Aturan Bisnis #6).
- `tanggal` tidak boleh di masa depan (relatif terhadap hari ini).
- Simpan bersifat upsert: kalau `(assignment_id, tanggal)` sudah punya record, update status/catatan-nya; kalau belum, buat baru.

### 3.2 Input Absensi — Alur Grid

1. Staf pilih Job dari dropdown. Sistem cari JobPeriod berstatus `aktif` milik Job itu.
2. Kalau Job tidak punya JobPeriod aktif → tampilkan pesan info, tidak ada grid.
3. Kalau ada → tampilkan grid: baris = Employee dari Assignment `is_current = true` pada JobPeriod itu, kolom = 7 hari minggu berjalan (Senin-Minggu berdasarkan `week_start` query param, default Senin minggu ini).
4. Klik sel → popover pilih status + catatan opsional → `POST` upsert → grid ter-update via partial reload (tanpa reload penuh halaman).
5. Sel yang tanggalnya di luar rentang assignment (§3.1) atau di masa depan → nonaktif, tidak bisa diklik.
6. Navigasi ‹ › mengganti `week_start` ke minggu sebelumnya/berikutnya.

### 3.3 Rekap Mingguan (Aturan Bisnis #7)

- Filter: rentang tanggal (wajib, `date_from`/`date_to`), Job (opsional — default semua Job).
- Tabel hasil: satu baris per Employee+Assignment, kolom status per hari dalam rentang, plus Job & No. Dokumen JobPeriod terkait.
- Hari dalam rentang tanggal filter yang overlap dengan rentang aktif assignment tapi **tidak ada record Attendance** → ditandai "Belum Diisi" (bukan dianggap Tidak Hadir).
- Ringkasan per baris: jumlah Hadir, Tidak Hadir, Izin dalam rentang filter.
- Export ke Excel dengan struktur kolom yang sama seperti tabel di halaman (termasuk kolom "Belum Diisi" per hari kalau relevan).

## 4. Fitur / Modul

- **Input Absensi** (`/attendance/input`): dropdown Job, navigasi minggu, grid interaktif. Admin & Staff Input saja (role:admin|staff_input).
- **Rekap Mingguan** (`/attendance/rekap`): filter tanggal + Job, tabel + ringkasan, tombol Export. Semua role bisa akses (read + export).
- **Dashboard**: card "Absensi Mingguan" diisi `count` sel belum diisi minggu berjalan (agregat semua Job dengan JobPeriod aktif).

## 5. Testing

- Validasi: tanggal di luar rentang assignment ditolak; assignment status selain aktif/diperbarui ditolak; assignment dengan tanggal_mulai di masa depan ditolak; upsert bekerja benar (update, bukan duplikat); unique constraint DB terpenuhi.
- Grid: hanya assignment is_current=true pada JobPeriod aktif yang muncul; Job tanpa JobPeriod aktif menampilkan pesan info; navigasi minggu menghasilkan rentang tanggal yang benar; sel di luar rentang assignment nonaktif.
- Rekap: penanda "belum diisi" muncul tepat untuk hari tanpa record dalam rentang aktif assignment; ringkasan hitung benar; filter Job mempersempit hasil; filter tanggal wajib divalidasi.
- Otorisasi: Viewer diblokir dari `store()` Input Absensi; Rekap & Export bisa diakses semua role.
- Dashboard: count "belum diisi" akurat.

## 6. Arsitektur Teknis

Tidak ada package baru (pakai `maatwebsite/excel` yang sudah terpasang untuk export). Dibangun langsung dengan shadcn/ui (Table, Popover, Select, Button) — modul baru, tidak ada halaman lama untuk dimigrasi. Pola `#[Fillable]` + `casts()` untuk model `Attendance`, `role:admin|staff_input` middleware untuk `AttendanceController::store()`, toast (`->with('success', ...)`) untuk aksi simpan, mengikuti seluruh konvensi yang sudah ditetapkan di modul-modul sebelumnya.
