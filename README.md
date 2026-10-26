# eRequisition (sample)

Mobile-first Laravel + Inertia/Vue **PWA** for product sample requisitions with a four-stage workflow:

**Requester → Department Manager → Inventory Manager → Warehouse** (release / line-change re-approval)

## Stack

- Laravel (Herd) + Breeze (Inertia + Vue 3)
- SQLite by default (swap to MySQL in `.env` if you prefer)
- Product catalog imported from `Book2.xlsx` → `database/data/products.json`
- PWA via `/manifest.webmanifest` + `/sw.js` (installable; offline shell only)

## Setup

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm run build
```

Open the Herd site (e.g. `http://sample.test`) or `php artisan serve`.

For local PWA/service-worker testing during development:

```bash
npm run dev
```

## Demo logins

Password for all accounts: **`password`**

| Role | Email |
|------|-------|
| Admin | `admin@example.com` |
| Requester (Sales) | `requester.sales@example.com` |
| Requester (Marketing) | `requester.marketing@example.com` |
| Requester (Medical) | `requester.medical@example.com` |
| Manager (Sales) | `manager.sales@example.com` |
| Manager (Marketing) | `manager.marketing@example.com` |
| Manager (Medical) | `manager.medical@example.com` |
| Head of Inventory | `inventory@example.com` |
| Warehouse | `warehouse@example.com` |

Each user should add a **signature** under Profile (draw or upload). Approvals stamp that signature onto the requisition. Admins manage users, departments, and signatures at `/admin`.

**Head of Inventory** (`inventory@example.com`) gets dashboard stats (pipeline, monthly volume, by department/type) plus **Reports** at `/inventory/reports` with filters and CSV export.

## Walkthrough

1. Log in as a **requester** → **New requisition** → add searchable products, accept declaration → **Submit**.
2. Log in as that department’s **manager** → approve (or send back / reject with a note).
3. Log in as **inventory** → approve → status becomes ready for warehouse. Open **Pick list** to print.
4. Log in as **warehouse**:
   - No line changes → **Release** → print **Final copy**.
   - Change qty/product + reason → submits to inventory for change review → inventory approves → warehouse **Release**s.
5. Send-back returns the request to the requester to edit and resubmit.

## Install as PWA

- **Android Chrome:** menu → *Install app* / *Add to Home screen*.
- **iOS Safari:** Share → *Add to Home Screen*.
- Prefer HTTPS (or `localhost`) for install prompts. Herd local sites work over the device’s LAN URL when trusted.

Live approvals still need a network connection; the service worker caches the app shell/assets.

## Regenerating the product catalog

```bash
python3 -m venv .venv
.venv/bin/pip install openpyxl
.venv/bin/python -c "..."  # see project history / convert Book2.xlsx → database/data/products.json
php artisan db:seed --class=ProductSeeder
```
