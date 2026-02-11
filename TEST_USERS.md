# Test User Credentials

This document contains all test/demo user accounts for the Society Management application.

## Default Test Accounts

| Role | Email | Password |
|------|-------|----------|
| **Society Admin** | admin@society.test | admin123 |
| **Pramukh** | pramukh@society.test | pramukh123 |
| **Building Admin** | building@society.test | building123 |
| **Member** | member@society.test | member123 |

---

## Account Details

### Society Admin
- **Email:** `admin@society.test`
- **Password:** `admin123`
- **Role:** society_admin
- **Permissions:** Full society management, all buildings, users, funds, meetings

### Pramukh (Society Head)
- **Email:** `pramukh@society.test`
- **Password:** `pramukh123`
- **Role:** society_pramukh
- **Permissions:** Society-level management, society fund, meetings, notes

### Building Admin
- **Email:** `building@society.test`
- **Password:** `building123`
- **Role:** building_admin
- **Building:** A Wing
- **Permissions:** Building management, members, maintenance, fund

### Member (Resident)
- **Email:** `member@society.test`
- **Password:** `member123`
- **Role:** member
- **Building:** A Wing
- **Permissions:** Personal dashboard, view meetings, pay maintenance

---

## Database Reference

These accounts are seeded in `backend/database.sql`:

```sql
-- Society Admin (ID: 1)
INSERT INTO users (id, name, email, password, role, society_id, building_id, status)
VALUES (1, 'Society Admin', 'admin@society.test', '$2y$10$YncKGDKZmqW4faWubTNNzO60TQ1Y6R0JHhn4oaguhtF6wejjzF8TG', 'society_admin', 1, NULL, 'active');

-- Pramukh (ID: 2)
INSERT INTO users (id, name, email, password, role, society_id, building_id, status)
VALUES (2, 'Society Pramukh', 'pramukh@society.test', '$2y$10$C/mc4fx9olG58kxJPdoxYed.da.9ypAkI.IxcEes93KWEe8M5HIYa', 'society_pramukh', 1, NULL, 'active');

-- Building Admin (ID: 3)
INSERT INTO users (id, name, email, password, role, society_id, building_id, status)
VALUES (3, 'Building Admin', 'building@society.test', '$2y$10$upRW87EYn2DAETBS9gqX3OMJpaZhXqtI68LALLzNqVjI4M/nDw9uC', 'building_admin', 1, 1, 'active');

-- Member (ID: 4)
INSERT INTO users (id, name, email, password, role, society_id, building_id, status)
VALUES (4, 'Member User', 'member@society.test', '$2y$10$amOXQixWmVR.StYiLEPFjOE9M4RJeH/i.lQQ7qjRcAOmqbwIRdlru', 'member', 1, 1, 'active');
```

---

**Note:** These are test accounts for development/demo purposes only. Change passwords in production.
