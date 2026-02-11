---
description: Common issues and fixes
---

# Troubleshooting

## 1) Login page not opening / ERR_TOO_MANY_REDIRECTS

### Symptoms

- Browser shows:
  - `ERR_TOO_MANY_REDIRECTS`
- URL might be:
  - `/building/dashboard.php`
  - `/admin/dashboard.php`

### Usual causes

- Session contains a partially-valid user (e.g. has `role` but missing `building_id` / `society_id`).
- Relative redirects causing mis-resolved paths.

### Fix / Steps

1. Clear cookies/site data for `http://localhost` or open **Incognito**.
2. Open login directly:
   - `http://localhost/divy1/society-app/auth/login.php`
3. If the loop continues:
   - Check `config/auth.php`
   - Confirm `redirect_to()` is used for redirects
   - Confirm scope checks are enforcing required IDs

## 2) CSS/JS not loading on deep links

### Symptoms

- Page opens, but styling is broken when opening URLs like:
  - `/building/members.php`

### Cause

- Assets referenced with relative paths (e.g. `../assets/...`) can break depending on the current URL.

### Fix

- Use app-root URLs via `app_base_url()` in shared includes.

## 3) Database connection failed

### Symptoms

- Page shows `Database connection failed.`

### Checklist

- MySQL is running in XAMPP
- Database imported:
  - import `database.sql` into phpMyAdmin
- `config/db.php` credentials match your environment

## 4) 404 Not Found

### Checklist

- Project folder should be inside `htdocs`:
  - `C:\xampp\htdocs\divy1\society-app`
- Correct URL:
  - `http://localhost/divy1/society-app/`

## 5) Demo users cannot login

### Checklist

- Ensure `database.sql` imported successfully
- Verify `users` table has rows:
  - `admin@society.test`
  - `pramukh@society.test`
  - `building@society.test`
  - `member@society.test`

Passwords (demo):

- `admin123`
- `pramukh123`
- `building123`
- `member123`

