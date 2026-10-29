# Deploying DOC-MON

How the live site gets updated, and who does what.

## How it works

DOC-MON has two parts that update in different ways:

| Part                                    | Updates how?                                                                       |
| --------------------------------------- | ---------------------------------------------------------------------------------- |
| **Code** (pages, logic)                 | **Automatically.** Anything merged into `main` goes live on **Render** by itself   |
| **Database tables** (on **TiDB Cloud**) | **By hand.** New tables or columns must be created by MJ                           |

New code that needs a table that doesn't exist yet **crashes the live site**. So new tables are always created **before** the code is merged.

---

## For everyone

Before you merge a pull request into `main`, check one thing:

> **Did I add a new file in `database/migrations/`?**

- **No** → merge as usual. Done ✅
- **Yes** → **don't merge yet.** Message MJ: _"my PR has a migration"_, and wait for _"done, you can merge"_.

That's all. You never need the database password.

---

## For MJ only: creating new tables on the live database

Needs the `.env.backup` file (TiDB **root** settings, git-ignored, with `APP_ENV=production` so Laravel asks before changing anything).

**1. Back up.** In the TiDB Cloud console, export the `docmon` database to your computer. TiDB's automatic backups are kept for only **1 day**.

> _Not walked through yet._ The first time you do it, write the exact clicks here.

**2. Preview.** Shows what will change, without changing anything:

```bash
php artisan migrate --pretend --env=backup
```

If you see `drop` or `rename`, stop and read [Removing or renaming columns](#removing-or-renaming-columns).

**3. Create the tables.** Answer `yes` when asked.

```bash
php artisan migrate --env=backup
```

**4. Check.** Every line should say `Ran`.

```bash
php artisan migrate:status --env=backup
```

**5. Tell the groupmate:** _"done, you can merge."_

### Removing or renaming columns

Adding is safe. **Removing or renaming** a column is not: the live code may still use it. Do it in two pull requests:

1. First, change the code so it no longer uses the column. Merge it.
2. Then add the migration that removes it.

---

## Never do these

| Never                                       | Why                                                                                                                                                                       |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan migrate:fresh --env=backup`    | Deletes **every table and every account**                                                                                                                                 |
| `php artisan migrate:rollback --env=backup` | Undoes migrations and can delete data                                                                                                                                     |
| `php artisan db:seed --env=backup`          | Adds demo accounts with the password `password`                                                                                                                           |
| Set Render's `DB_USERNAME` to **root**      | The site must use `docmon_app` (it can't delete tables). If the site can't connect, the password is probably mistyped: reset `docmon_app`'s password in TiDB and paste it into Render |
| Put a password in any file on GitHub        | Anyone can read it, even after you delete it. If it happens, **change the password right away**                                                                           |

Passwords live in Render's settings and in a password manager, never in the code or the docs.
