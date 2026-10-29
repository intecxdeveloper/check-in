# Interactive Expo Visitor Map

Map-first booth check-in experience: visitors join a growing company network on a live MapLibre map, while staff manage leads privately.

## Quick start

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

In another terminal for Vite HMR during development:

```bash
npm run dev
```

- Public map: [http://127.0.0.1:8000](http://127.0.0.1:8000)
- Staff desk: [http://127.0.0.1:8000/staff/login](http://127.0.0.1:8000/staff/login)
  - Email: `staff@expo.test`
  - Password: `password`

## Features (MVP)

- Full-screen MapLibre map with clustering, filters, live counters, and recent check-ins
- 5-step check-in wizard (profile, interests, company pin, consent, success)
- Nominatim-backed location search with manual pin confirmation
- Public company cards (no personal contact details)
- Staff auth, search, lead status/notes/assignment, CSV export
- Rate limiting, CSRF, duplicate check-in guard, privacy notice versioning

## Config

See `config/expo.php` and `.env`:

- `EXPO_MAP_STYLE` — MapLibre style URL (default OpenFreeMap dark)
- `EXPO_PRIVACY_VERSION` — privacy notice version stored with each visitor
- `EXPO_GEOCODER_USER_AGENT` — required Nominatim User-Agent
