<<<<<<< HEAD
# Society Management App (PHP + MySQL)

A lightweight Society/Building management system built with **plain PHP** (no framework) and **MySQL**, intended to run on **XAMPP (Windows)**.

## Features

- Role-based dashboards
  - `society_admin`
  - `society_pramukh`
  - `building_admin`
  - `member`
- Society and building fund tracking
- Meetings (society/building level)
- Notes
- Maintenance tracking
- Simple session-based authentication

## Tech Stack

- **PHP** (plain PHP pages)
- **MySQL**
- **XAMPP/Apache**
- UI
  - Some pages use **TailwindCSS CDN**
  - Some pages use simple local CSS/JS in `assets/`

## Project Structure

- `index.php`
  - Entry point. If logged in, redirects to the correct dashboard by role.
- `auth/`
  - `login.php` (login form + password verify)
  - `logout.php` (clears session)
- `config/`
  - `db.php` (PDO connection)
  - `auth.php` (session start, auth helpers, role redirects, scope checks)
- `admin/` (Society Admin screens)
- `pramukh/` (Society Pramukh screens)
- `building/` (Building Admin screens)
- `member/` (Member screens)
- `includes/`
  - Shared layout for some non-Tailwind pages
- `assets/`
  - `css/style.css`
  - `js/script.js`
- `database.sql`
  - MySQL schema + demo seed data

## Requirements

- XAMPP with:
  - Apache
  - MySQL
  - PHP 7.4+ recommended (works on PHP 8.x too)
- Browser (Chrome recommended)

## Installation / Setup (XAMPP)

1. Copy project into XAMPP htdocs
   - Path should look like:
     - `C:\xampp\htdocs\divy1\society-app`

2. Start services
   - Start **Apache**
   - Start **MySQL**

3. Create database + tables
   - Open phpMyAdmin:
     - `http://localhost/phpmyadmin`
   - Import `database.sql` from:
     - `society-app/database.sql`

4. Configure DB credentials (if needed)
   - File: `config/db.php`
   - Defaults:
     - host: `localhost`
     - db: `society_app`
     - user: `root`
     - pass: `` (empty)

## Run / URLs

- App root:
  - `http://localhost/divy1/society-app/`
- Login:
  - `http://localhost/divy1/society-app/auth/login.php`

Role dashboards (direct URLs):

- Society Admin:
  - `http://localhost/divy1/society-app/admin/dashboard.php`
- Society Pramukh:
  - `http://localhost/divy1/society-app/pramukh/dashboard.php`
- Building Admin:
  - `http://localhost/divy1/society-app/building/dashboard.php`
- Member:
  - `http://localhost/divy1/society-app/member/dashboard.php`

## Demo Logins (from `database.sql`)

These users are inserted by `database.sql` (bcrypt password hashes).

- Society Admin
  - email: `admin@society.test`
  - password: `admin123`
- Society Pramukh
  - email: `pramukh@society.test`
  - password: `pramukh123`
- Building Admin
  - email: `building@society.test`
  - password: `building123`
- Member
  - email: `member@society.test`
  - password: `member123`

## Authentication & Redirects (Important)

Auth logic lives in `config/auth.php`.

- `require_login()`
  - Redirects unauthenticated users to login.
- `require_role([...])`
  - Restricts access by role.
- `require_society_scope()` / `require_building_scope()`
  - Ensures the logged-in user has required `society_id` / `building_id`.
- `redirect_to('path')`
  - Redirect helper that always redirects using an **app-root** URL.

This project uses `app_base_url()` to build URLs safely so that pages still work when opened directly (deep links).

## Database Overview

See `database.sql` for full schema.

Main tables:

- `society`
- `buildings`
- `users`
- `society_fund`
- `building_fund`
- `meetings`
- `notes`
- `maintenance`

## Common Tasks

- Create buildings (Society Admin)
  - `admin/buildings.php`
- Create or update Building Admin login (Society Admin)
  - `admin/buildings.php` includes a “Create Login” section per building
- Add members (Building Admin)
  - `building/members.php`

## Troubleshooting

See:

- `docs/TROUBLESHOOTING.md`

## Developer Notes

See:

- `docs/ARCHITECTURE.md`

=======
# -society_management_system
>>>>>>> cc4cb9e47b5d4d90af409a28a83256651af4a504
