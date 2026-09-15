# Enkripsi At-Rest NIK & No. Rekening — Fase 1 NFR Gap

Status: Disetujui untuk implementasi
Tanggal: 15 September 2026
Induk spec: [PRD_Sistem_Manajemen_Pekerja_Pekerjaan_PR.md](../PRD_Sistem_Manajemen_Pekerja_Pekerjaan_PR.md) §9 (Kebutuhan Non-Fungsional: "NIK & No. Rekening adalah data sensitif — enkripsi at-rest (Laravel encrypted cast)")
Bergantung pada: [2026-09-11-employee-design.md](2026-09-11-employee-design.md) (model `Employee`, pola masking `App\Support\Masks::partial()`)

## 0. Keputusan dari Klarifikasi

| # | Topik | Keputusan |
|---|---|---|
| C1 | Trade-off pencarian NIK | Cari NIK jadi exact-match saja (16 digit penuh); cari Nama tetap substring seperti sekarang. Ini konsekuensi matematis dari enkripsi yang aman untuk data yang perlu dicari — tidak ada kompromi keamanan yang mengorbankan ini. |
| C2 | Pendekatan teknis | Enkripsi (`encrypted` cast Laravel, AES-256 non-deterministik) + blind index (`nik_hash`, HMAC-SHA256 deterministik) untuk `nik`. `no_rekening` cukup `encrypted` cast biasa tanpa hash (tidak pernah di-query exact-match di manapun dalam codebase). |
| C3 | Kunci HMAC | Env var terpisah `HASH_KEY`, bukan turunan dari `APP_KEY` — supaya rotasi `APP_KEY` di masa depan tidak ikut merusak `nik_hash`, dan dua kebutuhan kripto berbeda (enkripsi vs hashing) tidak berbagi satu rahasia. |
| C4 | Migrasi data lama | Artisan command sekali-jalan (`employees:encrypt-sensitive-data`), idempotent, dijalankan manual oleh admin/developer setelah `migrate` dan sebelum kode yang mengaktifkan cast `encrypted` di-deploy. |

## 1. Lingkup

**Masuk:**
- Kolom `nik` dan `no_rekening` di tabel `employees` dienkripsi at-rest.
- Kolom baru `nik_hash` (blind index deterministik) untuk mendukung pencarian exact-match dan constraint unique tanpa membocorkan pola data.
- Semua query exact-match pada `nik` (validasi unique create/update, cek duplikat di `EmployeesImport`/`AssignmentsImport`, cari NIK di halaman Index Karyawan) diarahkan ke `nik_hash`.
- Command Artisan idempotent untuk mengenkripsi data lama yang masih plaintext.
- Dokumentasi urutan deploy yang wajib diikuti.

**Tidak masuk (di luar scope):**
- Rotasi `APP_KEY`/`HASH_KEY` dan mekanisme re-enkripsi massal saat kunci dirotasi — ini kekhawatiran operasional masa depan, bukan bagian dari implementasi fitur ini.
- Perubahan pada field sensitif lain di luar `nik`/`no_rekening` (mis. tidak ada field sensitif lain di modul manapun yang butuh perlakuan sama saat ini).
- Perubahan pada pola masking (`Masks::partial()`) itu sendiri — tetap dipakai apa adanya, karena cast `encrypted` transparan (dekripsi otomatis saat properti diakses), jadi kode pemanggil masking tidak perlu berubah.
- Perubahan UI/UX pada kotak cari selain perilaku baru untuk NIK (tidak ada pesan error baru saat cari NIK sebagian tidak ketemu — cukup fallback diam-diam ke pencocokan Nama saja).

## 2. Model Data

**employees** (migrasi baru, menambah di atas skema yang sudah ada)

| Field | Perubahan | Catatan |
|---|---|---|
| nik | Tipe diperlebar dari `string(16)` ke `text`; constraint `unique()` **dihapus** | Menyimpan ciphertext (payload `encrypted` cast Laravel jauh lebih panjang dari 16 karakter, dan tidak bisa unik secara berguna karena tiap enkripsi menghasilkan output berbeda untuk isi yang sama) |
| no_rekening | Tipe diperlebar dari `string(50)` ke `text` | Menyimpan ciphertext, tidak ada constraint unique baik sebelum maupun sesudah perubahan ini |
| nik_hash | **Baru**: `string(64)`, `unique()`, indexed | HMAC-SHA256 (hex, 64 karakter) dari NIK asli — inilah yang jadi target constraint unique & pencarian exact-match, menggantikan peran `nik` yang sekarang terenkripsi |

Model `Employee`:
- `casts()` menambahkan `'nik' => 'encrypted', 'no_rekening' => 'encrypted'`.
- `#[Fillable]` tetap seperti sekarang (`nik_hash` TIDAK ditambahkan ke fillable — dihitung otomatis lewat model event, tidak pernah di-assign manual dari request).
- Method statis baru `Employee::hashNik(string $nik): string` — satu-satunya tempat yang menghitung HMAC, dipakai oleh model event dan semua query call site.
- `booted()` static method menambahkan listener `static::saving(fn (Employee $employee) => $employee->nik_hash = self::hashNik($employee->getAttribute('nik')))` — dijalankan otomatis setiap kali model akan disimpan, memastikan `nik_hash` selalu konsisten dengan `nik` tanpa perlu diingat manual di setiap call site yang menulis Employee.

`config/app.php` menambahkan entri baru: `'hash_key' => env('HASH_KEY')`, dibaca lewat `config('app.hash_key')`. `.env`/`.env.example` menambahkan `HASH_KEY=` (nilai contoh acak 64-hex-char di `.env.example`, nilai asli digenerate manual saat setup — lihat §7).

## 3. Aturan Bisnis — Perubahan Query

| Lokasi saat ini | Perubahan |
|---|---|
| `app/Http/Requests/StoreEmployeeRequest.php` — rule `'unique:employees,nik'` | Dihapus dari `rules()`; pengecekan dipindah ke `withValidator()` (pola sudah dipakai di `StoreAssignmentRequest`): `Employee::where('nik_hash', Employee::hashNik($nik))->exists()` → tambah error ke field `nik` kalau `true`. |
| `app/Http/Requests/UpdateEmployeeRequest.php` — rule `Rule::unique('employees', 'nik')->ignore($this->route('employee'))` | Sama seperti di atas, tambah `->where('id', '!=', $this->route('employee')->id)` pada query exists-check. |
| `app/Imports/EmployeesImport.php:39` — `Employee::where('nik', $nik)->first()` | → `Employee::where('nik_hash', Employee::hashNik($nik))->first()`. |
| `app/Imports/AssignmentsImport.php:44` — `Employee::where('nik', $nik)->first()` | → `Employee::where('nik_hash', Employee::hashNik($nik))->first()`. |
| `app/Http/Controllers/EmployeeController.php:28` — `->orWhere('nik', 'like', "%{$search}%")` | Diganti kondisional: kalau `preg_match('/^\d{16}$/', $search)`, tambahkan `->orWhere('nik_hash', Employee::hashNik($search))`; kalau tidak, NIK tidak diikutkan sama sekali dalam kondisi pencarian (hanya `nama` yang tetap `LIKE` seperti sekarang). |
| `app/Http/Controllers/EmployeeController.php` (baris masking `Masks::partial($employee->nik)`, 3 lokasi) | **Tidak berubah** — cast `encrypted` mendekripsi otomatis saat `$employee->nik` diakses, jadi nilai yang diterima `Masks::partial()` tetap plaintext seperti sekarang. |
| `app/Exports/EmployeesExport.php` — `$employee->nik`, `$employee->no_rekening` | **Tidak berubah** — otomatis terdekripsi lewat cast saat diakses. |
| `database/factories/EmployeeFactory.php` | **Tidak berubah** — `Employee::create([...])` tetap terima NIK plaintext sebagai input; cast yang menangani enkripsi saat simpan, `nik_hash` dihitung otomatis lewat model event. |

## 4. Migrasi Data Lama

Command baru: `app/Console/Commands/EncryptEmployeeSensitiveData.php`, dijalankan via `php artisan employees:encrypt-sensitive-data`.

Bekerja lewat **raw query builder** (`Illuminate\Support\Facades\DB::table('employees')`), bukan lewat Eloquent model `Employee` — supaya tidak bergantung pada cast `encrypted` yang belum aktif secara kode saat command ini pertama kali dijalankan (lihat §5 urutan deploy).

Untuk tiap baris di tabel `employees`:
1. Baca nilai `nik`/`no_rekening` mentah lewat query builder.
2. Coba `Illuminate\Support\Facades\Crypt::decryptString($value)`. Kalau **berhasil** (tidak melempar `DecryptException`) → nilai ini sudah pernah dienkripsi (baris sudah pernah diproses command ini sebelumnya, atau sudah dibuat lewat cast setelah deploy) → **lewati baris ini** (bikin command aman dijalankan berulang kali / idempotent).
3. Kalau **gagal** (melempar `DecryptException`) → nilai ini masih plaintext → enkripsi lewat `Crypt::encryptString($value)`, hitung `Employee::hashNik($nikPlaintext)` untuk kolom `nik_hash`, lalu `DB::table('employees')->where('id', $id)->update([...])`.

Command mencetak ringkasan di akhir: jumlah baris yang dienkripsi, jumlah yang dilewati (sudah terenkripsi sebelumnya).

## 5. Urutan Deploy (WAJIB diikuti, prosedur manual — tidak diotomasi)

1. `php artisan migrate` — hanya mengubah struktur tabel (perlebar `nik`/`no_rekening`, tambah kolom `nik_hash`). Data lama belum tersentuh, aplikasi masih baca/tulis `nik`/`no_rekening` sebagai plaintext di titik ini (kode lama belum di-deploy, cast belum aktif).
2. `php artisan employees:encrypt-sensitive-data` — mengenkripsi seluruh data lama yang masih plaintext dan mengisi `nik_hash` untuk semua baris.
3. Deploy kode yang mengaktifkan cast `encrypted` di model `Employee` + semua perubahan query di §3.

**Peringatan:** kalau urutan dibalik (kode dengan cast `encrypted` aktif di-deploy sebelum langkah 2 selesai), setiap pembacaan model `Employee` akan melempar `DecryptException` karena mencoba mendekripsi nilai yang masih plaintext — aplikasi akan error total di semua fitur yang menyentuh data Karyawan.

## 6. Testing

- Validasi unique menolak NIK duplikat (lewat `nik_hash`) saat create dan update (termasuk kasus update ke NIK milik sendiri — tidak boleh false-positive tertolak).
- `EmployeesImport`/`AssignmentsImport` tetap mendeteksi NIK duplikat dengan benar lewat `nik_hash`.
- Cari dengan NIK 16 digit penuh yang valid → karyawan ditemukan.
- Cari dengan NIK sebagian (kurang dari 16 digit, atau mengandung karakter non-digit) → tidak ditemukan lewat NIK (fallback pencocokan Nama saja, tidak error).
- Cari dengan Nama (substring) → tetap berfungsi seperti sekarang, tidak terpengaruh perubahan ini.
- Masking untuk role Viewer tetap menampilkan NIK/No. Rekening yang benar (ter-mask dari nilai plaintext yang sudah didekripsi transparan).
- `no_rekening` round-trip: simpan → baca kembali → nilai identik dengan input asli.
- Command `employees:encrypt-sensitive-data`: jalan pertama kali mengenkripsi semua baris plaintext yang ada; jalan kedua kali (tanpa data baru) tidak mengubah apa pun (0 baris diproses, semua dilewati).
- **Bukti keamanan langsung**: query SQL mentah (`DB::table('employees')->first()` atau setara) menunjukkan isi kolom `nik`/`no_rekening` adalah ciphertext (tidak bisa dibaca sebagai NIK/nomor rekening asli), bukan plaintext — ini validasi paling penting dari seluruh fitur ini.

## 7. Arsitektur Teknis

Tidak ada package baru — memakai fitur bawaan Laravel (`encrypted` cast, `Illuminate\Support\Facades\Crypt`) dan fungsi PHP native (`hash_hmac()`). `HASH_KEY` digenerate manual sekali saat setup (mis. lewat `php -r "echo bin2hex(random_bytes(32));"`) dan ditambahkan ke `.env` setiap environment (dev, staging, production) — **tidak boleh sama antar-environment** dan **tidak boleh di-commit ke git** (sama seperti perlakuan `APP_KEY`). `.env.example` cukup memuat `HASH_KEY=` kosong sebagai placeholder dengan komentar mengingatkan untuk digenerate.
