# Auth & Role Dasar — Fase 1 Sub-Project 1

Status: Disetujui untuk implementasi
Tanggal: 10 September 2026
Induk spec: [2026-09-09-worktrack-mvp-design.md](2026-09-09-worktrack-mvp-design.md) §8.5 (User & Role Management), K2 (akses NIK/rekening)

## 0. Keputusan dari Klarifikasi

| # | Topik | Keputusan |
|---|---|---|
| D1 | Registrasi user baru | Hanya Admin yang bisa membuat akun — registrasi publik Breeze dinonaktifkan |
| D2 | Role default user baru | `viewer` (paling terbatas), kecuali Admin memilih role lain saat membuat akun |
| D3 | Bootstrap Admin pertama | Artisan command interaktif `worktrack:create-admin`, tidak ada kredensial hardcoded |

## 1. Lingkup

**Masuk:**
- Seed 3 role: `admin`, `staff_input`, `viewer` (via `spatie/laravel-permission`, paket sudah terpasang saat setup project).
- Command `worktrack:create-admin` untuk bootstrap akun admin pertama.
- Menonaktifkan registrasi publik Breeze.
- Modul User Management (Admin-only): Index (list user + role), Create (form + pilih role, default `viewer`), Edit (ubah role user).
- Share info role user yang login ke semua halaman Inertia via `HandleInertiaRequests`.

**Tidak masuk (di luar scope sub-project ini):**
- Detail permission granular per fitur (mis. siapa bisa lihat NIK penuh) — itu diimplementasikan di masing-masing modul (Employee, dst.) menggunakan role yang sudah tersedia dari sub-project ini.
- Reset password oleh Admin untuk user lain, deaktivasi akun — tidak diminta PRD untuk Fase 1, YAGNI.
- UI untuk membuat/mengedit permission secara dinamis — 3 role tetap (fixed), tidak perlu CRUD role.

## 2. Model Data

Tidak ada tabel baru. Menggunakan tabel yang sudah dipublish `spatie/laravel-permission` (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) dari task setup project.

## 3. Alur & Aturan

1. `RoleSeeder` (dijalankan via `DatabaseSeeder`) membuat role `admin`, `staff_input`, `viewer` jika belum ada (`Role::firstOrCreate(['name' => ...])`).
2. `php artisan worktrack:create-admin` — prompt interaktif nama/email/password (validasi email unik, password minimal sesuai default Laravel), buat `User`, assign role `admin`. Bisa dijalankan berkali-kali untuk membuat admin tambahan (tidak ada batasan jumlah admin).
3. Rute `GET/POST /register` (dari `routes/auth.php`, hasil generate Breeze) dihapus. Link "Register" di halaman login (jika ada dari template Breeze) dihapus.
4. Rute `/users` (Index, Create store, Edit update) hanya bisa diakses role `admin`, digate middleware `role:admin` dari Spatie.
5. Form Create User: input nama, email, password, dan dropdown role (`admin`/`staff_input`/`viewer`) — default terpilih `viewer` jika Admin tidak mengubahnya. Setelah submit, user baru langsung dapat role tersebut (bukan role kosong lalu di-assign terpisah).
6. `HandleInertiaRequests::share()` menambahkan `auth.user.roles` (array nama role user yang login) ke setiap response Inertia, supaya frontend modul lain bisa membaca role tanpa request tambahan. Ini hanya untuk keperluan UI (menyembunyikan tombol/menu) — validasi akses sesungguhnya tetap di server (middleware/policy), tidak boleh mengandalkan frontend saja.

## 4. Testing

- Command `worktrack:create-admin` membuat user baru dengan role `admin` ter-assign.
- `GET /register` mengembalikan 404 (rute dihapus).
- User dengan role `staff_input`/`viewer` yang mengakses `/users` mendapat 403.
- Admin membuat user baru tanpa memilih role → user tersebut punya role `viewer`.
- Admin membuat user baru dengan role `staff_input` yang dipilih eksplisit → user tersebut punya role `staff_input`, bukan `viewer`.
- Inertia shared prop `auth.user.roles` berisi role yang benar untuk user yang login.

## 5. Arsitektur Teknis

Tidak ada perubahan dari spec induk §8. Menggunakan `spatie/laravel-permission` yang sudah terpasang; tidak menambah package baru.
