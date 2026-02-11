---
description: Codebase architecture overview
---

# Architecture

This project is a **plain-PHP (multi-page)** application running on Apache (XAMPP). Each screen is a `.php` file that:

1. Includes `config/auth.php` (starts session + provides helpers)
2. Enforces access rules (`require_role`, `require_society_scope`, `require_building_scope`)
3. Reads/writes data with PDO (`$pdo` from `config/db.php`)
4. Renders HTML

There is no dedicated router/controller layer; **file paths are the routes**.

## High-level flow

- **Entry point:** `index.php`
  - If not logged in -> goes to login
  - If logged in -> redirects to role dashboard

- **Login:** `auth/login.php`
  - Validates credentials using `password_verify`
  - Saves user data to `$_SESSION['user']`
  - Redirects to dashboard by role

- **Logout:** `auth/logout.php`
  - Clears session, removes cookie
  - Redirects to login

## Authentication module (`config/auth.php`)

Key helpers:

- `app_base_url()`
  - Computes the app base path using `$_SERVER['SCRIPT_NAME']`
  - Used to build safe app-root URLs.

- `redirect_to($path)`
  - Sends a `Location:` header using `app_base_url()`.

- `is_logged_in()`
  - Returns true only if `$_SESSION['user']` has required fields.

- `require_login()`
  - Redirects to login if not authenticated.

- `require_role(array $allowedRoles)`
  - Ensures the logged in user has one of the allowed roles.

- `require_society_scope()`
  - Ensures `society_id` exists in session.

- `require_building_scope()`
  - Ensures `building_id` exists in session.

- `redirect_by_role()`
  - Sends the user to the correct dashboard.

### Why app-root redirects matter

When visiting deep links (e.g. `/building/dashboard.php`), relative redirects like `../auth/login.php` can mis-resolve depending on the current folder.

This codebase uses `redirect_to()` + `app_base_url()` to avoid that.

## Database access (`config/db.php`)

- Uses PDO with:
  - `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
  - `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`
  - `PDO::ATTR_EMULATE_PREPARES => false`

All pages assume `$pdo` is available after including `config/auth.php`.

## Modules (folders)

- `admin/`
  - Society admin dashboard and management screens.

- `pramukh/`
  - Society pramukh dashboard and limited screens.

- `building/`
  - Building admin dashboard and building-scoped screens.

- `member/`
  - Member dashboard and member-scoped screens.

- `includes/`
  - Shared layout for some screens.
  - Note: Some dashboards use Tailwind and have their own HTML layout.

- `assets/`
  - Shared CSS/JS for non-Tailwind screens.

## Data model (summary)

See `database.sql` for full schema.

- `society` has many `buildings`
- `users` belong to a `society` and optionally to a `building`
- `society_fund` is scoped to society
- `building_fund` is scoped to building
- `meetings` can be society-level or building-level
- `maintenance` records monthly charges per member
- `notes` are associated to a user and have a level (`society`, `building`, `personal`)

## Security notes

- Passwords are stored as bcrypt hashes (via `password_hash`)
- Prepared statements are used for DB operations
- All output should be escaped using `e()` when printing user-provided values

