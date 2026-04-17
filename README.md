# CaruCars

Dealership website for Caru Cars in Miami.

## Architecture

```
GoDaddy (DNS)  →  Netlify (CDN + static hosting)  →  Hostinger (PHP endpoints)

                                    ┌──────────────────────────────────┐
                                    │  DealerCenter                    │
                                    │  pushes CSV via FTP 3x/day to:   │
                                    │  Hostinger /public_html/         │
                                    └──────────────────────────────────┘
                                                   │
                                                   ▼
                                       Hostinger /feed.php
                                       (parses CSV → JSON)
                                                   │
                                                   ▼
                                    ┌──────────────────────────────────┐
                                    │  GitHub Actions: Sync Inventory  │
                                    │  runs 3x/day                     │
                                    │  - curl feed.php                 │
                                    │  - wrap as inventory-data.js     │
                                    │  - commit to main                │
                                    └──────────────────────────────────┘
                                                   │
                                                   ▼
                                    ┌──────────────────────────────────┐
                                    │  Netlify auto-deploys on push    │
                                    │  carucars.com serves fresh JS    │
                                    └──────────────────────────────────┘
```

## What each piece does

- **GoDaddy** — owns the carucars.com domain. DNS points to Netlify.
- **Netlify** — serves the website. Publishes from this GitHub repo. Any file
  not in the repo falls through to Hostinger via `_redirects`.
- **Hostinger** — runs the PHP endpoints. Receives DealerCenter CSV pushes.

## Pieces that matter

### Customer-facing
- `index.html`, `inventory.html`, `vehicle.html`, etc. — the site
- `inventory-data.js` — auto-generated vehicle array (see sync flow)
- `styles.css`, `main.js`, `inventory.js`, `vehicle.js` — front-end

### Hostinger PHP
- `feed.php` — parses latest DealerCenter CSV, returns JSON vehicle array
- `health-check.php` — pipeline health for monitoring
- `lead-to-crm.php` — contact form → DealerCenter CRM (ADF/XML email)
- `send-application.php` — credit application → PDF → email
- `sms-notify.php` — SMS notifications
- `fpdf.php`, `tcpdf_min.php` — PDF library

### GitHub Actions
- `deploy-hostinger.yml` — pushes PHP + assets to Hostinger on commit
- `sync-inventory.yml` — pulls fresh inventory 3x/day, commits JS
- `inventory-health-check.yml` — monitors pipeline, alerts on failure

## Manual ops

```bash
# Force an inventory sync right now
gh workflow run sync-inventory.yml -R WayofMint/carucars

# Check pipeline health
curl -s "https://yellowgreen-emu-225498.hostingersite.com/health-check.php?key=carucars-health-2026" | jq
```

## Secrets

Stored in GitHub Actions secrets on the WayofMint/carucars repo:
- `FTP_USERNAME` — Hostinger FTP user
- `FTP_PASSWORD` — Hostinger FTP password
