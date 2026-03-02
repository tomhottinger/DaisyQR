# DaisyQR

DaisyQR is a PHP + SQLite web app to manage reusable QR codes.

A QR code in DaisyQR points to a short internal URL like `/a1b2c3d4e5f6`. The destination URL behind that code can be programmed or changed later. This is useful for printed labels, flyers, posters, packaging, inventory tags, and access links where the target may change over time.

## What It Is Good For

- Reusable QR labels with stable printed codes and changeable destinations
- Team-managed QR inventory with user ownership and admin roles
- Fast mobile scanning with camera + manual fallback
- Controlled release of codes (`private` vs `public`)
- Print-ready code sheets (A6 layouts)
- PWA install on mobile and Android share-target workflow

## Features

- Central routing via `index.php` and `.htaccess`
- SQLite storage (`data/qrcodes.db`)
- User sessions with cookie-based login
- Admin area for:
  - Code management
  - Batch code generation
  - Print layouts
  - User management (admin only)
- QR image generation via redirect to `api.qrserver.com`
- PWA manifest + service worker caching

## Tech Stack

- PHP (no framework)
- SQLite3 extension
- Apache rewrite rules (`.htaccess`)
- Vanilla JavaScript frontend

## Project Structure

- `index.php` - central router
- `config.php` - base URL/path and runtime config
- `db.php` - DB schema, migrations, auth/session helpers, code logic
- `handlers/` - page and API handlers
- `assets/` - CSS, JS, icon
- `manifest.webmanifest` + `sw.js` - PWA setup
- `data/` - SQLite DB and bootstrap credentials files

## Requirements

- PHP 8.x recommended
- PHP extensions: `sqlite3`
- Apache with `mod_rewrite`
- HTTPS recommended (required for reliable camera + PWA behavior on mobile)

## Setup

1. Deploy repository to your PHP web root.
2. Ensure Apache can rewrite to `index.php` (`.htaccess` enabled).
3. Ensure `data/` is writable by the web server user.
4. Open the app in a browser.

On first run, the app auto-creates DB tables and bootstrap admin access:

- `data/first_admin_token.txt`
- `data/first_admin_password.txt`

## Usage

1. Login (`/login`) as admin/user.
2. Generate codes in `Admin -> Generieren`.
3. Print QR sheets in `Admin -> Drucken` or apply codes physically.
4. Program a code by scanning it, then assigning a destination URL.
5. Optionally mark programmed codes as `public` in `Admin -> Codes`.

### Scan Decision Logic

When a code is scanned, DaisyQR decides whether to:

- open target URL directly
- request login
- send owner to programming form
- return an error (unknown code, not programmed, forbidden)

This logic is exposed through `/api/resolve/{codeId}` and used by UI flows.

## Interfaces

### 1) Public Scanner (`/`)

- Camera scanner (BarcodeDetector API)
- Manual code input fallback
- Resolves scanned codes through API
- Can operate in share-mode (if URL was sent via Android share target)

### 2) Login (`/login`)

- Username/password login
- Optional QR-token login flow (`/auth/{token}`)

### 3) Admin Codes (`/admin/codes`)

- Filter, sort, paginate code list
- Edit/program code
- Toggle `public`/`private`
- Reset programming or delete code
- Shows scan counters

### 4) Admin Generate (`/admin/generate`)

- Create 1..100 codes at once
- Shows generated QR previews

### 5) Admin Print (`/admin/print`)

- Print selected/generated codes
- Layouts: `single`, `a6-2`, `a6-3`, `a6-4`, `a6-grid`

### 6) Admin Users (`/admin/users`, admin only)

- Create users and admins
- Set/reset passwords
- Show auth login links/QRs

## API

Base: same origin as app, typically `/api/...`.

Auth: session cookie (`session_token`) is used where required.

### `GET /api/codes`

List codes.

Response:

```json
{
  "success": true,
  "codes": [
    {
      "id": "a1b2c3d4e5f6",
      "target_url": "https://example.com",
      "title": "Example",
      "scan_count": 3,
      "created_at": "...",
      "updated_at": "...",
      "url": "https://host/a1b2c3d4e5f6"
    }
  ]
}
```

### `GET /api/codes/{id}`

Get one code.

### `PUT|PATCH /api/codes/{id}`

Update code data.

Body:

```json
{
  "target_url": "https://example.com",
  "title": "Optional title"
}
```

### `DELETE /api/codes/{id}`

Delete only programming, or delete whole code.

Body (optional):

```json
{
  "complete": true
}
```

### `POST /api/generate`

Generate codes.

Body:

```json
{
  "count": 10
}
```

### `POST /api/program`

Program one code.

Body:

```json
{
  "code_id": "a1b2c3d4e5f6",
  "target_url": "https://example.com",
  "title": "Optional",
  "force": false
}
```

- Returns `409` with `requires_force: true` if already programmed and `force` is false.

### `GET /api/resolve/{id}`

Resolve scan action.

Possible `action` values:

- `OPEN_CODE`
- `REDIRECT_TO_PROGRAMMING`
- `PROMPT_LOGIN`
- `ERROR_CODE_NOT_FOUND`
- `ERROR_NOT_PROGRAMMED`
- `ERROR_NOT_OWNER`

### `GET /api/scan/{id}`

Authenticated scan/open check; increments scan count on success.

### `POST /api/share/scan/{id}`

Apply pending shared URL (from PWA share-target) to scanned code.

### `POST /api/share/overwrite/{id}`

Confirm overwrite when target code already has URL.

### QR image endpoints

- `GET /api/qr.php?id={codeId}&size=200`
- `GET /api/qr/{codeId}`
- `GET /api/qr-auth/{authToken}`

All redirect to `https://api.qrserver.com/v1/create-qr-code/...`.

## PWA Installation on Mobile

Prerequisites:

- App served over HTTPS
- Open app URL in browser

### Android (Chrome/Edge)

1. Open DaisyQR in browser.
2. Tap browser menu.
3. Tap `Install app` or `Add to Home screen`.
4. Confirm installation.

After install:

- App runs standalone (without browser UI).
- Android share sheet can send URLs directly to DaisyQR (`share_target` in manifest).

### iPhone (Safari)

1. Open DaisyQR in Safari.
2. Tap Share button.
3. Select `Add to Home Screen`.
4. Confirm with `Add`.

Notes:

- iOS does not support the same web share-target flow as Android in many versions.
- Scanner needs camera permission.

## Security Notes

- Keep app behind HTTPS.
- Protect server access to `data/` (already blocked via `.htaccess`).
- Rotate bootstrap credentials after first setup.
- Use strong admin passwords.

## License

This project is licensed under the MIT License.
See [LICENSE](./LICENSE).
