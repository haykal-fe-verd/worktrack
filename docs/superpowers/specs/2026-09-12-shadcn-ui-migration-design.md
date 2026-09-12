# Migrasi UI ke shadcn/ui — Fase 1 (Setup + Layout + Auth)

Status: Disetujui untuk implementasi
Tanggal: 12 September 2026
Induk spec: [2026-09-09-worktrack-mvp-design.md](2026-09-09-worktrack-mvp-design.md) (tidak mengubah aturan bisnis, hanya lapisan tampilan)
Bergantung pada: Semua modul yang sudah ada (Auth, Employee, Job, Users) — migrasi bertahap, tidak mengubah logika backend

## 0. Keputusan dari Klarifikasi

| # | Topik | Keputusan |
|---|---|---|
| U1 | Urutan kerja | Implementasi Assignment module (spec [2026-09-12-assignment-design.md](2026-09-12-assignment-design.md)) selesai dulu memakai UI lama; migrasi shadcn ini dikerjakan setelahnya, mencakup Assignment juga |
| U2 | Pembagian scope | Dipecah jadi 5 sub-project berurutan (lihat §1), masing-masing spec+plan+implementasi sendiri |
| U3 | Palet warna | Tetap pakai palet PLN (blue/navy/yellow/teal), di-mapping jadi CSS variables tema shadcn |
| U4 | Struktur navigasi | Tetap topbar horizontal (tidak ganti ke sidebar) |
| U5 | Dashboard bento-grid | Konsep dipertahankan, `BentoCard` diimplementasi ulang di atas shadcn `Card` |
| U6 | Create/Edit/Show | Diganti dari halaman penuh terpisah menjadi Dialog/Sheet shadcn dibuka dari halaman Index — mengurangi jumlah halaman, berlaku mulai sub-project 3 (Employee) |
| U7 | Detail dengan konten banyak | Tetap Dialog (bukan Sheet), tapi `max-width` dibuat lebih besar |
| U8 | Halaman Import | Jadi Sheet (panel geser), bukan halaman penuh |
| U9 | Notifikasi aksi | Toast/Sonner otomatis setiap aksi selesai (create/update/delete/import/dll), lewat shared flash message dari backend |

## 1. Lingkup — Dekomposisi Sub-Project

Migrasi ini dipecah menjadi 5 sub-project berurutan (masing-masing dapat spec & plan sendiri saat gilirannya tiba):

1. **Setup shadcn + Layout & Auth** (dispesifikasikan penuh di dokumen ini) — install & konfigurasi shadcn, tema warna PLN, komponen dasar, infrastruktur toast, migrasi `AuthenticatedLayout`/`GuestLayout`/nav, halaman Auth (Login, ForgotPassword, ResetPassword, ConfirmPassword, VerifyEmail), Profile.
2. **Dashboard** — `BentoCard` di atas shadcn `Card`, tetap konsep bento-grid, tambah toast kalau relevan.
3. **Employee** — Index, Create/Edit/Show jadi Dialog, Import jadi Sheet, toast di setiap aksi.
4. **Job** — Index, Create/Show(+riwayat periode) jadi Dialog besar, alur Perbarui PR (3 skenario) jadi Dialog bertingkat, Import jadi Sheet, toast di setiap aksi.
5. **Users management** — Index, Create/Edit jadi Dialog, toast di setiap aksi.

(Assignment module dibangun **setelah** sub-project 5 selesai, langsung memakai shadcn — tidak ada migrasi ulang untuk Assignment.)

Sub-project 2-5 didetailkan lewat siklus brainstorming masing-masing saat gilirannya tiba (pola sama seperti modul-modul sebelumnya). Dokumen ini fokus ke sub-project 1.

## 2. Sub-Project 1: Setup shadcn + Layout & Auth

### 2.1 Setup Tooling

- Konteks environment saat ini: Tailwind yang **aktif** adalah v3 classic (`@tailwind base/components/utilities` di `resources/css/app.css`, `tailwind.config.js` bergaya JS, plugin `postcss`/`autoprefixer`). DevDependency `@tailwindcss/vite` v4 ada di `package.json` tapi **tidak** di-wire ke `vite.config.ts` — sisa scaffold yang tidak dipakai, dibiarkan sebagaimana adanya (di luar scope).
- Install shadcn via `npx shadcn@latest init`, mode Vite + Tailwind v3 (mendeteksi setup yang aktif di atas).
- Path alias `@/*` sudah ada di `tsconfig.json` → dipakai untuk `@/components/ui` dan `@/lib/utils` (fungsi `cn()`).
- Komponen dasar yang di-generate lewat `npx shadcn@latest add`: `button`, `input`, `label`, `card`, `table`, `dialog`, `sheet`, `dropdown-menu`, `badge`, `alert`, `select`, `checkbox`, `separator`, `sonner`.

### 2.2 Tema Warna

- `tailwind.config.js` diperluas dengan CSS custom properties bergaya shadcn (`--primary`, `--secondary`, `--destructive`, `--muted`, `--accent`, `--border`, dst.) didefinisikan di `resources/css/app.css` di bawah `:root`.
- Mapping: `--primary` = pln.blue (`#00AFF0`), `--primary-foreground` = putih; `--secondary`/`--accent` mengacu ke pln.navy/pln.teal; `--destructive` tetap merah standar (dipakai utk aksi hapus/nonaktifkan, bukan warna brand). Namespace `pln.*` di Tailwind config **tetap dipertahankan** (dipakai di tempat yang butuh warna brand eksplisit, mis. logo/aksen), berdampingan dengan token semantik shadcn.
- Semua komponen shadcn yang di-generate otomatis mewarisi tema ini tanpa override manual per halaman.

### 2.3 Infrastruktur Toast/Sonner

- `app/Http/Middleware/HandleInertiaRequests.php::share()` ditambah:
  ```php
  'flash' => [
      'success' => fn () => $request->session()->get('success'),
      'error' => fn () => $request->session()->get('error'),
  ],
  ```
- Komponen `<Toaster />` (dari `sonner`, hasil `shadcn add sonner`) dipasang sekali di `AuthenticatedLayout` dan `GuestLayout`.
- Hook `useFlashToast()` (baru, `resources/js/hooks/use-flash-toast.ts`): baca `usePage().props.flash`, `useEffect` yang memanggil `toast.success(flash.success)` / `toast.error(flash.error)` saat prop berubah dan tidak kosong.
- Sub-project 1 hanya membangun infrastruktur ini + memasangnya di controller Auth/Profile yang sudah punya alur redirect dengan pesan (mis. update password, update profile, delete account). Controller modul lain (Employee/Job/Users) ditambah pesan flash saat migrasinya masing-masing (sub-project 2-5).

### 2.4 Migrasi Komponen

| Komponen lama | Pengganti shadcn |
|---|---|
| `PrimaryButton`, `SecondaryButton`, `DangerButton` | `Button` (variant `default`/`secondary`/`destructive`) |
| `TextInput`, `InputLabel`, `InputError` | `Input` + `Label` + teks error di bawahnya (pola form shadcn standar) |
| `Dropdown` (menu profil) | `DropdownMenu` |
| `Modal` | `Dialog` |
| `Checkbox` | `Checkbox` |
| `NavLink`, `ResponsiveNavLink` | Tetap topbar (U4), styling ulang jadi link biasa dengan style shadcn (bukan `NavigationMenu` — menu sedikit, tidak perlu primitive kompleks) |

Tidak disentuh di sub-project ini: `BentoCard`, `Pagination`, `ApplicationLogo` (masuk sub-project 2+ sesuai kebutuhan halamannya).

### 2.5 Halaman yang Dimigrasi

- `resources/js/Layouts/AuthenticatedLayout.tsx`, `GuestLayout.tsx`
- `resources/js/Pages/Auth/Login.tsx`, `ForgotPassword.tsx`, `ResetPassword.tsx`, `ConfirmPassword.tsx`, `VerifyEmail.tsx`
- `resources/js/Pages/Profile/Edit.tsx` + partial-nya (`UpdateProfileInformationForm`, `UpdatePasswordForm`, `DeleteUserForm`)

Semua halaman ini **tetap halaman penuh** (bukan Dialog) — pola U6 (Create/Edit/Show jadi Dialog) berlaku untuk entitas CRUD list (Employee/Job/Users), bukan untuk Auth/Profile yang memang halaman mandiri.

## 3. Pola Teknis Dialog/Sheet (acuan untuk sub-project 3-5 & Assignment)

Didokumentasikan di sini karena ini keputusan arsitektur lintas sub-project, meski implementasinya baru terjadi mulai sub-project 3:

- Route & controller backend **tidak berubah** — `employees.create`, `employees.show/{employee}`, `jobs.create`, dst. tetap ada, tetap `Inertia::render()` ke komponen halaman yang sama.
- Komponen halaman untuk Create/Edit/Show sederhana dibungkus `Dialog` dengan `open` selalu `true` saat halaman itu dirender (karena route itu sendiri = "state terbuka"); form/detail sederhana → `Dialog` ukuran standar.
- Detail dengan konten banyak (Job Show + riwayat periode, nanti JobPeriod Show + riwayat Assignment) → `Dialog` juga tapi dengan `className` `max-width` lebih besar (mis. `sm:max-w-2xl` atau lebih), bukan `Sheet`.
- Import (upload + ringkasan hasil) → `Sheet` (`side="right"`), bukan halaman penuh maupun `Dialog`.
- Menutup Dialog/Sheet (klik X, klik luar, atau `Escape`) → `router.visit(route('<modul>.index'))` untuk kembali ke daftar (mengganti URL, konsisten dengan riwayat browser).
- Alur bertingkat (mis. "Perbarui PR" 3 skenario di Job) → Dialog dalam Dialog sesuai kebutuhan alurnya, detail ditentukan saat brainstorming sub-project 4.

## 4. Testing

- Sub-project 1: assert halaman Auth/Profile tetap merender komponen yang benar (Inertia test biasa, cuma ganti assertion nama komponen kalau file di-rename — nama file/komponen halaman tidak berubah, cuma isi internalnya).
- Flash message: test baru — controller yang di-update (Auth/Profile) mengembalikan session flash `success`/`error` yang benar, shared prop `flash` muncul di response Inertia.
- Tidak ada perubahan pada test Feature yang sudah ada di modul Employee/Job/Users (backend logic tidak berubah) — migrasi mereka baru terjadi di sub-project 3-5 dan test-nya disesuaikan saat itu (mis. `assertSee`/Inertia component assertion untuk Dialog vs page terpisah, kalau ada perbedaan struktur).

## 5. Arsitektur Teknis

- Package baru: `shadcn/ui` (bukan dependency npm biasa — CLI men-generate source komponen langsung ke `resources/js/components/ui/`, jadi bukan devDependency tambahan di `package.json` selain util pendukungnya seperti `class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-react`, `@radix-ui/*` per komponen yang dipakai, dan `sonner`).
- Tidak ada perubahan backend selain `HandleInertiaRequests::share()` (flash) — logika bisnis/validasi/otorisasi semua modul tidak berubah, murni migrasi lapisan tampilan + pola interaksi (Dialog/Sheet vs halaman terpisah).
