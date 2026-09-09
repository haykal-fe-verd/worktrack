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
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm install && npm run build"
```

Aplikasi tersedia di `http://localhost:8080`.

## Services

| Service | Peran |
|---|---|
| `app` | PHP-FPM + Laravel |
| `web` | Nginx |
| `postgres` | Database |
| `redis` | Cache & queue driver |
| `queue-worker` | Proses import Excel besar secara async |
| `scheduler` | Tugas terjadwal |
