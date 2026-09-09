# WorkTrack

Sistem manajemen pekerja, pekerjaan & PR untuk Helper Non-Rutin. Lihat
[docs/PRD_Sistem_Manajemen_Pekerja_Pekerjaan_PR.md](docs/PRD_Sistem_Manajemen_Pekerja_Pekerjaan_PR.md)
dan [docs/superpowers/specs/2026-09-09-worktrack-mvp-design.md](docs/superpowers/specs/2026-09-09-worktrack-mvp-design.md)
untuk detail requirement dan desain Fase 1.

## Stack

Laravel + Inertia.js + React (TypeScript) + Tailwind CSS, PostgreSQL 16, Redis 7, Docker Compose.

## Menjalankan secara lokal

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm install --legacy-peer-deps && npm run build"
```

Aplikasi tersedia di `http://localhost:8080`.

## Development (hot reload)

Untuk menjalankan Vite dev server dengan HMR tanpa perlu meng-install Node di
host, jalankan container Node sekali pakai yang meng-expose port 5173:

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html -p 5173:5173 node:20-alpine sh -c "npm install --legacy-peer-deps && npm run dev -- --host"
```

> Catatan: `--legacy-peer-deps` diperlukan karena `vite@^8` di `package.json`
> lebih baru dari rentang peer dependency yang didukung `@vitejs/plugin-react`
> saat ini; tanpa flag ini `npm install` gagal dengan error `ERESOLVE`.

Setelah server berjalan (menampilkan log `Local: http://localhost:5173/` dan
`Network:` yang bind ke `0.0.0.0:5173`), Laravel/Inertia akan otomatis memuat
asset dari Vite dev server tersebut selama `APP_ENV=local`. Tekan `Ctrl+C`
untuk menghentikan server saat selesai.

## Services

| Service | Peran |
|---|---|
| `app` | PHP-FPM + Laravel |
| `web` | Nginx |
| `postgres` | Database |
| `redis` | Cache & queue driver |
| `queue-worker` | Proses import Excel besar secara async |
| `scheduler` | Tugas terjadwal |
