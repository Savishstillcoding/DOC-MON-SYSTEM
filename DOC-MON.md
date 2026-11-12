# DOC-MON

**Document monitoring for student organizations at Leyte Normal University (LNU).**

A student officer submits a ticket for the organization's physical papers (resolutions, proposals, budgets). The papers then move through the organization's signatories in order, and everyone can see where each paper is, who has it, and whether that signatory is available right now. The pilot organization is **DIGITS**.

A System Analysis and Design (SAD) capstone project by **Carusa, Labastida, Ligutan and Magno**.

Live site: <https://doc-mon-system.onrender.com>

**New here?** [HOW-IT-WORKS.md](HOW-IT-WORKS.md) explains how the system flows, with diagrams.

---

## What it does

| Role | Can do |
|---|---|
| **Student Officer** (Secretary / Deputy Secretary) | Submit a ticket with one document (Single) or several (Batch); follow each of the organization's papers on the Dashboard, My Tickets and Signatories pages; see the signatories' live availability; resubmit a returned paper |
| **Signatory** (e.g. Adviser, SSC President, ODSOA, VPSD) | See the papers waiting at their step; approve, return for revision or reject each document (remarks required for return/reject), or approve a whole Batch at once; set their availability ("In a Meeting · back at 2:00 PM"); see their history |
| **Admin** | Verify new accounts against their uploaded school ID (approve, or reject with a reason the person sees at log-in); edit, deactivate and reactivate users; add organizations; set each organization's approval route; read the Activity Logs |

**How a paper moves:** each document goes through the route one step at a time. A returned paper goes back to the **same** signatory after the officer fixes it, and earlier approvals stay valid. The last signatory on the route gives the final approval.

**Other details:**
- Accounts are reviewed by the admin before they can log in, so nobody can pose as an officer or a signatory.
- An organization's officers share its tickets, so the Deputy Secretary can carry on for the Secretary; each ticket shows who filed it.
- Officer pages refresh their data by themselves (every 20 seconds) without reloading.
- Every important action is recorded in a write-only activity log, including log-ins (failed ones too) and every view of an uploaded ID.
- **Telegram notifications:** after connecting Telegram on their Profile, officers hear when papers are approved, returned or rejected, and signatories hear when a paper is waiting for them.
- **Forgot password?** emails a one-time link (valid 60 minutes) through Brevo.
- **Privacy:** a public Privacy Notice, consent at sign-up, and a yearly admin review of accounts older than 4 years.

**Not built yet:** SMS notifications.

---

## Recent changes

Newest first, important things only.

- **2026-11-12 (database):** Tickets get `active_ref_no` so a fully rejected ticket's Ref No. can be used again (the screens follow in the next update), and users get `availability_updated_at` so "Back at 2:00 PM" can show when it was set. Run the migration on TiDB before merging.
- **2026-11-12:** Easier to read and use: darker grey text (passes the WCAG AA contrast check on every page), a "Skip to content" link for keyboard users, the password rule shown under the box, no more cut-off placeholders, and a properly styled Email box on Forgot / Reset Password.
- **2026-11-11:** Security: passwords need letters and numbers and can't be a known leaked one; changing a password signs out other devices; log-ins and ID views are recorded; a per-account log-in limit; anti-clickjacking headers; `docmon:create-admin` adds an admin.
- **2026-11-07:** Overdue and Due Soon badges (3 days or fewer). Officers now see the Date Needed, and the signatory's queue shows the most urgent papers first.
- **2026-11-05:** Privacy Notice at `/privacy`. Sign-up needs consent, the phone number is optional, and existing accounts accept once at their next log-in.
- **2026-11-04:** An organization's officers now share its tickets, so the Deputy can carry on for the Secretary. Each ticket shows who filed it.
- **2026-11-04:** Forgot password emails a one-time link through Brevo.
- **2026-11-04:** The search boxes work, and the 🔔 links to Telegram settings.
- **2026-11-04:** Telegram notifications for officers and signatories.

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

### Optional: Telegram and email

Both stay off until you fill in their keys in `.env`; the rest of DOC-MON works without them. Use **your own** test bot and Brevo account, never the live ones.

| Feature | `.env` lines | Where to get them |
|---|---|---|
| Telegram notifications | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` (without the @) | Telegram → **@BotFather** → `/newbot` |
| Password reset email | `MAIL_MAILER=brevo`, `BREVO_API_KEY`, `MAIL_FROM_ADDRESS` (the sender you verified in Brevo) | brevo.com → **SMTP & API → API Keys**: Restricted, *Transactional email: All* |

Without a Brevo key, keep `MAIL_MAILER=log`: reset emails are then written to `storage/logs/laravel.log` instead of being sent.

> **The Brevo key expires after 90 days without use.** If "Forgot password?" stops sending emails (the log says `Password reset email failed`), make a new key in Brevo and update `.env` and Render's Environment settings.

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
