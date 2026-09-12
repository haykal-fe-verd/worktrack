# Data Job & Periode PR — Fase 1 Sub-Project 3

Status: Disetujui untuk implementasi
Tanggal: 12 September 2026
Induk spec: [2026-09-09-worktrack-mvp-design.md](2026-09-09-worktrack-mvp-design.md) §6.3 (Job, JobPeriod), §7 (Aturan Bisnis #2, #3), §8.2 (Data Job & Periode PR)
Bergantung pada: [2026-09-10-auth-roles-design.md](2026-09-10-auth-roles-design.md) (role), [2026-09-11-employee-design.md](2026-09-11-employee-design.md) (pola CRUD+import+export yang diteruskan ke modul ini)

## 0. Keputusan dari Klarifikasi

| # | Topik | Keputusan |
|---|---|---|
| J1 | Scope Import Excel | Disertakan sekarang, konsisten dengan modul Karyawan |
| J2 | Akses CRUD | Sama seperti Karyawan: Admin & Staff Input CRUD, Viewer read-only |
| J3 | Alur "Perbarui PR" | Tombol di halaman detail Job → dialog pilih 1 dari 3 skenario (Aturan Bisnis #2 a/b/c) |
| J4 | Peringatan jumlah TK vs Assignment | Ditunda ke sub-project Assignment; `jumlah_tk_rencana` cuma disimpan sebagai field di Fase ini |
| J5 | Uniqueness `no_dokumen` | Unik global di seluruh tabel `job_periods`, lintas jenis dokumen |
| J6 | Status Job | Toggle manual oleh staf (pola sama seperti status Karyawan), bukan turunan otomatis dari JobPeriod |
| J7 | Parsing kolom gabungan saat konflik | Prioritas PR > DO > WO kalau sel berisi lebih dari satu referensi |

## 1. Lingkup

**Masuk:**
- CRUD Job (nama, lokasi, klien, status) dan JobPeriod (di bawah Job) dengan validasi `no_dokumen` unik global
- Alur keputusan "Perbarui PR" dengan 3 skenario sesuai Aturan Bisnis #2
- Riwayat JobPeriod per Job (list, bukan timeline visual khusus)
- Import Excel (format Image 2 PRD, termasuk parsing kolom gabungan `NO. DO/PR/WO`) dengan laporan error
- Export Excel (Admin/Staff Input saja)
- Pagination + search + filter status di list Job

**Tidak masuk (di luar scope sub-project ini):**
- Peringatan jumlah TK rencana vs assignment aktif (Aturan Bisnis #3) — field `jumlah_tk_rencana` disimpan, logikanya menyusul saat modul Assignment ada (J4)
- Relasi ke Assignment/Employee — itu sub-project #4 berikutnya
- Pencocokan otomatis "lanjutan vs job baru" saat import — setiap baris import selalu jadi Job+JobPeriod independen (lihat §3.4); mencocokkan hasil import ke Job existing adalah koreksi manual staf lewat UI, di luar scope
- Timeline visual/grafis riwayat PR — cukup list terurut

## 2. Model Data

**jobs**

| Field | Tipe | Catatan |
|---|---|---|
| id | bigint, PK | |
| nama_pekerjaan | string(255) | |
| lokasi | string(255), nullable | |
| klien | string(255), nullable | |
| status | string(20) | enum `aktif`/`selesai`, default `aktif`, toggle manual (J6) |
| created_at, updated_at | timestamp | |

**job_periods**

| Field | Tipe | Catatan |
|---|---|---|
| id | bigint, PK | |
| job_id | bigint, FK → jobs | |
| jenis_dokumen | string(10) | enum `PR`/`PO`/`DO`/`WO` |
| no_dokumen | string(50), **unique global** | J5 — unik di seluruh tabel, lintas jenis_dokumen |
| kode_po | string(50), nullable | |
| nilai_po | decimal(15,2) | |
| tanggal_mulai | date | |
| tanggal_selesai | date, nullable | |
| jumlah_tk_rencana | integer | Disimpan saja di Fase ini (J4) |
| status | string(20) | enum `aktif`/`berakhir`/`diperbarui`, default `aktif` |
| previous_period_id | bigint, FK → job_periods, nullable | Diisi hanya untuk skenario (a) "lanjutan" |
| keterangan | text, nullable | |
| created_at, updated_at | timestamp | |

Tidak ada tabel/field tambahan untuk skenario (b)/(c) — (b) cukup Job+JobPeriod baru tanpa relasi apa pun ke yang lama; (c) tidak membuat record sama sekali.

## 3. Aturan Bisnis

### 3.1 Validasi

- `nama_pekerjaan`, `jenis_dokumen`, `no_dokumen`, `nilai_po`, `tanggal_mulai`, `jumlah_tk_rencana`: wajib.
- `no_dokumen`: unik di tabel `job_periods` (ignore diri sendiri saat update), lintas semua `jenis_dokumen`.
- `tanggal_selesai`: nullable, kalau diisi harus `>= tanggal_mulai`.
- `status` Job dan `status` JobPeriod: hanya bisa diubah lewat aksi toggle/aksi khusus (bukan field bebas di form edit biasa) — pola sama seperti Employee (§3.1 spec Employee).

### 3.2 Membuat Job Baru (dari nol)

Form tunggal: data Job + data JobPeriod pertamanya sekaligus. Tidak melalui alur 3 skenario karena belum ada PR sebelumnya untuk "diperbarui". JobPeriod pertama otomatis `status = aktif`, `previous_period_id = null`.

### 3.3 Perbarui PR (Aturan Bisnis #2)

Tombol "Perbarui PR" muncul di halaman detail Job **hanya jika Job punya JobPeriod berstatus `aktif`**. Staf memilih 1 dari 3 skenario:

- **(a) Lanjutan job sama**: form JobPeriod baru di bawah Job yang sama. Setelah disimpan: JobPeriod baru dapat `previous_period_id` = id JobPeriod lama; JobPeriod lama di-update jadi `status = berakhir`.
- **(b) Job baru sama sekali**: form Job baru + JobPeriod baru dalam satu alur (sama seperti §3.2), sama sekali tanpa relasi ke Job/JobPeriod lama. Job lama tidak diubah.
- **(c) Assignment saja**: tidak ada form. Sistem menampilkan pesan info: "Job & Periode PR tidak berubah — kelola penugasan pekerja di modul Assignment." (Modul Assignment belum ada, jadi ini murni pesan informatif tanpa aksi lanjutan di Fase ini.)

### 3.4 Import

1. Header Excel: `NO. DO/PR/WO`, `URAIAN PEKERJAAN`, `JUMLAH TK`, `MULAI TANGGAL`, `S/D TANGGAL`, `PO`, `NILAI PO`, `KETERANGAN`.
2. Parsing `NO. DO/PR/WO`: cari pola `PR:\s*(\S+)`, lalu `DO:\s*(\S+)`, lalu `WO:\s*(\S+)` — ambil match **pertama yang ditemukan** sesuai urutan prioritas itu (J7) sebagai `jenis_dokumen`+`no_dokumen`. Kalau tidak ada satu pun pola yang cocok → baris gagal, alasan "Format No. Dokumen tidak dikenali".
3. Per baris valid: **selalu buat Job baru + JobPeriod baru** (tidak mencoba mencocokkan ke Job yang sudah ada — lihat §1). `nama_pekerjaan` = `URAIAN PEKERJAAN`. `jumlah_tk_rencana` dari `JUMLAH TK` (ambil angka, buang satuan seperti "Org" kalau ada). `nilai_po` dari `NILAI PO`. `tanggal_mulai`/`tanggal_selesai` dari `MULAI TANGGAL`/`S/D TANGGAL`. `kode_po` dari `PO`. `keterangan` dari `KETERANGAN`.
4. Kalau `no_dokumen` hasil parsing sudah ada di database → baris gagal, alasan "No. Dokumen sudah terdaftar" (tidak ada logika "skip kalau sama persis" seperti di Karyawan — untuk JobPeriod, duplikat `no_dokumen` apa pun kondisinya dianggap butuh review manual, karena mencocokkan "baris yang sama" di banyak field sekaligus terlalu berisiko salah).
5. Baris gagal dikumpulkan jadi laporan error Excel (kolom asli + `Alasan Gagal`), didownload sekali, tidak ada tabel/antrian tambahan — pola identik dengan modul Karyawan.

### 3.5 Export

- Tombol Export di list Job, Admin/Staff Input saja.
- Export semua JobPeriod (join ke Job untuk `nama_pekerjaan`), tidak ada data sensitif jadi tidak perlu masking.
- Kolom: Nama Pekerjaan, Jenis Dokumen, No. Dokumen, Kode PO, Nilai PO, Tanggal Mulai, Tanggal Selesai, Jumlah TK Rencana, Status.

## 4. Fitur / Modul

- **Index Job** (`/jobs`): list (nama, klien, lokasi, status, jumlah periode), search (nama/klien), filter status, pagination 20/halaman. Tombol Tambah Job/Import/Export untuk Admin/Staff Input.
- **Create Job** (`/jobs/create`): form gabungan Job + JobPeriod pertama.
- **Detail Job** (`/jobs/{job}`): info Job, daftar JobPeriod (terbaru dulu), tombol "Perbarui PR" (kondisional ada JobPeriod aktif) dan toggle status Job — keduanya Admin/Staff Input saja.
- **Perbarui PR — pilih skenario**: dialog/halaman pilih (a)/(b)/(c), redirect ke form yang sesuai atau tampilkan pesan info untuk (c).
- **Perbarui PR — form (a)**: form JobPeriod baru untuk Job yang sama.
- **Perbarui PR — form (b)**: form Job baru + JobPeriod baru (reuse form Create Job).
- **Import** (`/jobs/import`): upload, ringkasan hasil (berhasil/gagal), download laporan error.
- **Export**: aksi langsung dari Index, Admin/Staff Input saja.

## 5. Testing

- Validasi: field wajib, `no_dokumen` unik (create & update, lintas jenis dokumen), `tanggal_selesai >= tanggal_mulai`.
- Otorisasi: Viewer diblokir dari semua aksi tulis (create/update/perbarui-PR/toggle-status/import/export); Admin & Staff Input bisa semua.
- Skenario (a): JobPeriod baru dapat `previous_period_id` benar, JobPeriod lama jadi `berakhir`, Job tetap sama.
- Skenario (b): Job baru + JobPeriod baru dibuat, tidak ada relasi ke Job/JobPeriod lama.
- Tombol "Perbarui PR" hanya muncul kalau ada JobPeriod berstatus `aktif`.
- Import: parsing prioritas PR>DO>WO, baris tanpa pola valid gagal, duplikat `no_dokumen` gagal, setiap baris valid selalu jadi Job+JobPeriod baru (tidak pernah menyatu ke Job lain).
- Export: berisi semua JobPeriod dengan nama Job terkait, hanya bisa diakses Admin/Staff Input.

## 6. Arsitektur Teknis

Tidak ada perubahan dari spec induk §8/§10. Menggunakan `maatwebsite/excel` yang sudah terpasang, pola CRUD/import/export/masking-helper mengikuti struktur yang sudah dibangun di modul Karyawan (`app/Support`, `app/Imports`, `app/Exports`, route grouping `role:admin|staff_input`). Tidak menambah package baru.
