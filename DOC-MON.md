# DOC-MON

**Document monitoring for student organizations at Leyte Normal University (LNU).**

A student officer submits a ticket for the organization's physical papers (resolutions, proposals, budgets). The papers then move through the organization's signatories in order, and everyone can see where each paper is, who has it, and whether that signatory is available right now. The pilot organization is **DIGITS**.

A System Analysis and Design (SAD) capstone project by **Carusa, Labastida, Ligutan and Magno**.

Live site: <https://doc-mon-system.onrender.com>

---

## What it does

| Role | Can do |
|---|---|
| **Student Officer** (Secretary / Deputy Secretary) | Submit a ticket with one document (Single) or several (Batch); follow each paper on the Dashboard, My Tickets and Signatories pages; see the signatories' live availability; resubmit a returned paper |
| **Signatory** (e.g. Adviser, SSC President, ODSOA, VPSD) | See the papers waiting at their step; approve, return for revision or reject each document (remarks required for return/reject), or approve a whole Batch at once; set their availability ("In a Meeting · back at 2:00 PM"); see their history |
| **Admin** | Verify new accounts against their uploaded school ID (approve, or reject with a reason the person sees at log-in); edit, deactivate and reactivate users; add organizations; set each organization's approval route; read the Activity Logs |

**How a paper moves:** each document goes through the route one step at a time. A returned paper goes back to the **same** signatory after the officer fixes it, and earlier approvals stay valid. The last signatory on the route gives the final approval.

**Other details:**
- Accounts are reviewed by the admin before they can log in, so nobody can pose as an officer or a signatory.
- Officer pages refresh their data by themselves (every 20 seconds) without reloading.
- Every important action is recorded in a write-only activity log.

**Not built yet:** SMS (Semaphore) and Telegram notifications, and password reset by email.

---

## Tech stack

- **Laravel 13** (PHP 8.4 or newer), Blade views
- Plain CSS and JavaScript in `public/css` and `public/js` (no Tailwind, no npm build step)
- **SQLite** for local development, **TiDB Cloud** (MySQL-compatible) for the live site
- **Docker**, hosted on **Render**
- **PHPUnit** for the automated tests

---

## Run it on your laptop

You need PHP 8.4+ and Composer 2. Node.js is **not** needed.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed      # creates the tables and the demo accounts
php artisan serve               # then open http://127.0.0.1:8000
```

### Demo accounts

These exist **only on your local database**: the seeder refuses to run on the live site. The password for all of them is `password`.

| Role | Log in at | ID |
|---|---|---|
| Student Officer | `/login/officer` | `2201234` |
| Student Officer, still **pending** (cannot log in yet) | `/login/officer` | `2209876` |
| Signatory, step 1 (Adviser) | `/login/signatory` | `EMP-0451` |
| Signatories, steps 2 to 4 | `/login/signatory` | `EMP-0452`, `EMP-0122`, `EMP-0300` |
| Admin | `/login/admin` | `admin` |

To start over with a clean local database, run `php artisan migrate:fresh --seed`. Only ever do this **locally**, never against the live database.

### After pulling a teammate's changes

```bash
composer install       # if composer.json or composer.lock changed
php artisan migrate    # adds any new tables; keeps your data
```

### With Docker (MySQL, like the live site)

```bash
docker compose up --build
```

The site runs on <http://localhost:8000> and phpMyAdmin on <http://localhost:8080>.

---

## Tests

```bash
php artisan test
```

The tests run on a temporary in-memory database, so they never touch your local or the live data. Run them before every commit.

---

## How we work

**Branches.** Every change goes on the branch for its type, then into `main` through a pull request:

| Change | Branch |
|---|---|
| New feature, page or button | `additional-features-branch` |
| Bug fix, cleanup, refactor, Docker / deploy / config, docs | `system-updates-branch` |
| New migration (table or column) | `tables-database-branch` |

**Commit titles** start with `[MAJOR UPDATE]`, `[UPDATE]`, `[CHANGES]` or `[FIXED]`.

**Rules:**
- Anything merged into `main` goes live on Render automatically.
- A pull request with a **new migration** must wait until the live database has the new table: see [DEPLOY.md](DEPLOY.md).
- Never edit a migration that has already been committed. Make a new one instead.
- Never commit `.env` or any password.
