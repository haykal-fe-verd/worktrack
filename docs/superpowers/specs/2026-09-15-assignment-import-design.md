# Import Excel Assignment — Fase 1 Sub-Project 7

Status: Disetujui untuk implementasi
Tanggal: 15 September 2026
Induk spec: [2026-09-09-worktrack-mvp-design.md](2026-09-09-worktrack-mvp-design.md); [2026-09-12-assignment-design.md](2026-09-12-assignment-design.md) (keputusan A9: import ditunda dari sub-project Assignment)
Bergantung pada: [2026-09-11-employee-design.md](2026-09-11-employee-design.md) (pola Import Employee), [2026-09-12-job-period-design.md](2026-09-12-job-period-design.md) (pola Import Job, `JobPeriod.no_dokumen`), [2026-09-12-assignment-design.md](2026-09-12-assignment-design.md) (model `Assignment`, aturan bisnis manual)

## 0. Keputusan dari Klarifikasi

| # | Topik | Keputusan |
|---|---|---|
| B1 | Kolom Excel | NIK, No. Dokumen, Tanggal Mulai, Tanggal Selesai (opsional), Tarif Jual, Tarif Bayar |
| B2 | Status & is_current per baris | Otomatis dari Tanggal Selesai: kosong atau ≥ hari ini → `Aktif`+`is_current=true`; sudah lewat → `Selesai`+`is_current=true` (tetap current karena tidak ada penerus, sama seperti alur manual "Akhiri Penugasan") |
| B3 | Duplikat (kombinasi Karyawan+Periode sudah punya Assignment) | Selalu error, baris dilewati — tidak ada pengecekan "skip kalau identik" seperti Employee import, karena riwayat Assignment historis lebih sensitif untuk didiamkan |
| B4 | Status JobPeriod tujuan | Tidak dibatasi — boleh periode Aktif maupun Selesai (migrasi data historis) |
| B5 | Status Karyawan tujuan | Tidak disyaratkan Aktif — NIK cukup ditemukan di database (migrasi data historis melibatkan karyawan yang mungkin sudah nonaktif) |

## 1. Lingkup

**Masuk:**
- Import massal Assignment dari file Excel: cocokkan NIK → Employee, No. Dokumen → JobPeriod, validasi, buat record `Assignment`
- Laporan error yang bisa diunduh untuk baris yang gagal (pola sama seperti Employee/Job import)
- Halaman `Assignments/Import.tsx` (Sheet) + route di bawah `role:admin|staff_input`

**Tidak masuk (di luar scope sub-project ini):**
- Update/re-import Assignment yang sudah ada (append-only, sama seperti CRUD manual — tidak ada "update via import")
- Import Attendance historis (sudah ditegaskan tidak masuk scope Fase 1 di spec Attendance)
- Perubahan pada alur CRUD manual Assignment yang sudah ada — sub-project ini murni menambah satu jalur input baru
- Peringatan jumlah TK (Aturan Bisnis #3 dari spec Assignment) untuk baris hasil import — peringatan itu tampil di halaman JobPeriod, bukan di alur import

## 2. Model Data

Tidak ada perubahan skema. Menggunakan tabel `assignments` dan model `Assignment` yang sudah ada (lihat [2026-09-12-assignment-design.md](2026-09-12-assignment-design.md) §2). Setiap baris sukses menghasilkan satu record baru dengan `previous_assignment_id = null` (tidak ada rantai versi untuk data hasil import — riwayat versi hanya terbentuk lewat alur manual perubahan tanggal setelahnya).

## 3. Aturan Bisnis — Validasi per Baris

Validasi dijalankan berurutan; begitu satu langkah gagal, baris dicatat ke laporan error dengan alasan spesifik dan lanjut ke baris berikutnya (tidak menghentikan seluruh import — pola sama seperti `EmployeesImport`/`JobsImport`):

1. **NIK**: wajib diisi, format 16 digit angka. Cocokkan ke `Employee::where('nik', $nik)->first()`. Tidak ditemukan → error "NIK tidak terdaftar". Status Employee tidak divalidasi (keputusan B5).
2. **No. Dokumen**: wajib diisi. Cocokkan ke `JobPeriod::where('no_dokumen', $noDokumen)->first()`. Tidak ditemukan → error "No. Dokumen tidak terdaftar". Status JobPeriod tidak dibatasi (keputusan B4).
3. **Tanggal Mulai**: wajib diisi, harus berupa tanggal yang valid (parse dengan `Carbon::parse()`, format tanggal Excel apa pun yang bisa dibaca `maatwebsite/excel` sebagai `DateTimeInterface` atau string, mengikuti pola `JobsImport::parseDate()`).
4. **Tanggal Selesai**: opsional. Kalau diisi, harus tanggal valid dan `>= Tanggal Mulai`. Kalau tidak valid (format salah) atau `< Tanggal Mulai` → error.
5. **Tarif Jual / Tarif Bayar**: opsional, numerik. Kosong → `null`. Diisi → parse dengan parser angka format Indonesia (sama seperti `JobsImport::parseIndonesianNumber()` — titik sebagai pemisah ribuan, koma sebagai desimal).
6. **Duplikat**: `Assignment::where('employee_id', $employee->id)->where('job_period_id', $jobPeriod->id)->exists()` — dicek tanpa filter `status`/`is_current` (mencakup seluruh riwayat, bukan cuma yang aktif). Ada → error "Karyawan sudah punya penugasan pada periode ini" (keputusan B3).

Baris yang lolos semua langkah:
- `status` & `is_current`: Tanggal Selesai kosong ATAU `>= hari ini` → `AssignmentStatus::Aktif`, `is_current = true`. Tanggal Selesai `< hari ini` → `AssignmentStatus::Selesai`, `is_current = true` (keputusan B2).
- `previous_assignment_id = null`.
- `created_by` = id user yang menjalankan import (dari `$request->user()`).
- `tarif_jual`/`tarif_bayar` = hasil parse langkah 5 (bisa `null`).

## 4. Fitur / Modul

- **Import Assignment** (`/assignments/import`, GET untuk form + hasil, POST untuk upload): halaman `Assignments/Import.tsx` (komponen `Sheet`, pola sama persis dengan `Employees/Import.tsx`/`Jobs/Import.tsx`). Diakses `role:admin|staff_input` saja (write-gating konsisten dengan CRUD Assignment manual).
- **Unduh Laporan Error** (`/assignments/import/errors`): mengunduh file Excel berisi baris yang gagal + alasannya, memakai `ImportErrorsExport` yang sudah ada (generik, menerima `Collection` + array heading kustom). Session key `assignment_import_errors` dihapus setelah diunduh; `404` kalau tidak ada error tersimpan.
- Tidak ada perubahan pada halaman/route CRUD Assignment manual yang sudah ada.

## 5. Struktur Laporan Error

Kolom (nilai mentah dari baris Excel + alasan gagal), mengikuti pola `JobImportController::HEADINGS`:

```
NIK, NO. DOKUMEN, TANGGAL MULAI, TANGGAL SELESAI, TARIF JUAL, TARIF BAYAR, Alasan Gagal
```

Flash message setelah import: sukses (`errorCount === 0`) → `"Import selesai: {created} berhasil, {errorCount} gagal."`; ada error (`errorCount > 0`) → `"Import selesai dengan {errorCount} baris gagal ({created} berhasil). Lihat laporan error untuk detail."` (pola sama persis dengan `JobImportController::store()`).

## 6. Testing

- NIK tidak ditemukan → baris masuk error, tidak ada Assignment dibuat.
- No. Dokumen tidak ditemukan → baris masuk error.
- Format NIK bukan 16 digit angka → error.
- Tanggal Mulai kosong/format tidak valid → error.
- Tanggal Selesai diisi tapi `< Tanggal Mulai` atau format tidak valid → error.
- Duplikat kombinasi Karyawan+Periode (sudah ada Assignment apa pun untuk pasangan itu, aktif maupun selesai) → error, tidak membuat baris baru.
- Tanggal Selesai kosong → `status=Aktif`, `is_current=true`.
- Tanggal Selesai di masa depan → `status=Aktif`, `is_current=true`.
- Tanggal Selesai di masa lalu → `status=Selesai`, `is_current=true`.
- Tarif Jual/Tarif Bayar kosong → tersimpan `null`.
- Tarif format Indonesia (mis. `1.234.567,89`) terparse jadi `1234567.89`.
- Import ke JobPeriod berstatus Selesai tetap berhasil (tidak ditolak).
- Import untuk Employee berstatus nonaktif tetap berhasil (tidak ditolak).
- Role selain `admin`/`staff_input` (mis. `viewer`) diblokir (403) dari route import.
- Unduh laporan error mengosongkan session setelah diunduh; percobaan unduh kedua tanpa error baru → 404.
- File bukan `.xlsx`/`.xls` atau melebihi batas ukuran → ditolak validasi upload (`mimes:xlsx,xls`, `max:5120`, sama seperti Employee/Job import).

## 7. Arsitektur Teknis

Tidak ada package baru — memakai `maatwebsite/excel` yang sudah terpasang. `ImportErrorsExport` (sudah ada, generik) dipakai ulang tanpa perubahan. Kelas baru `App\Imports\AssignmentsImport` (implements `ToCollection`, `WithHeadingRow`) mengikuti struktur `JobsImport`/`EmployeesImport` persis (properti publik `$errors`, `$created`; method privat untuk parsing tanggal & angka Indonesia — bisa diekstrak jadi trait/helper bersama kalau muncul kebutuhan ketiga kalinya, tapi untuk sekarang duplikasi kecil ini konsisten dengan pola dua import sebelumnya). Controller baru `App\Http\Controllers\AssignmentImportController` (method `create()`, `store()`, `downloadErrors()`) mengikuti struktur `JobImportController` persis. Komponen shadcn `Sheet`/`Button`/`Input` yang sudah ada dipakai untuk halaman `Assignments/Import.tsx`.
