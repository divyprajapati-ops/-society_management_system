# Society Management App - Production Checklist & Integration Guide

## 1. BACKEND SECURITY IMPROVEMENTS (COMPLETED)

### Enhanced `config/auth.php` with:
- **CSRF Protection**: Token generation, validation, and meta tag helpers
- **Session Security**: Regenerate ID every 30 minutes, store IP & User Agent
- **API Response Helpers**: `json_success()`, `json_error()`, `json_response()`
- **Ownership Verification**: `verify_society_ownership()`, `verify_building_ownership()`
- **Security Headers**: X-Frame-Options, X-XSS-Protection, X-Content-Type-Options
- **Input Sanitization**: `input_string()`, `input_int()`, `input_email()`
- **Pagination Helper**: `paginate()` with automatic count and limit
- **Activity Logging**: `log_activity()` for audit trail

### Enhanced `auth/login.php` with:
- Failed login attempt logging (security monitoring)
- Session fixation prevention (regenerate ID on login)
- IP and User Agent storage for optional session binding

### Database Updates:
- Added `last_login` column to `users` table
- Added `activity_logs` table for audit trail
- Added indexes for performance optimization

---

## 2. STITCH UI INTEGRATION PATTERN

### Step 1: Include CSRF Meta Tag in HTML Head
```html
<head>
    <?php echo csrf_meta(); ?>
    <!-- Other head elements -->
</head>
```

### Step 2: Include API Client JavaScript
```html
<script src="../assets/js/api-client.js"></script>
```

### Step 3: Create API Endpoint (copy from `admin/api_example.php`)

### Step 4: Connect Stitch Form to Backend
```javascript
// Initialize API client
const api = new SocietyAPI();

// Handle form submission
const form = document.getElementById('myForm');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
        const result = await handleFormSubmit(form, api, 'admin/api.php');
        // Success - refresh data or redirect
        window.location.reload();
    } catch (error) {
        // Error already shown via toast
        console.error('Form submission failed:', error);
    }
});
```

### Step 5: Load Data into UI Components
```javascript
// Load dashboard stats
async function loadDashboard() {
    try {
        const response = await api.getDashboardStats();
        const stats = response.data;
        
        // Update UI elements
        document.getElementById('buildings-count').textContent = stats.buildings;
        document.getElementById('residents-count').textContent = stats.residents;
        document.getElementById('meetings-count').textContent = stats.meetings;
        document.getElementById('fund-balance').textContent = '₹' + stats.fund_balance.toFixed(2);
    } catch (error) {
        console.error('Failed to load dashboard:', error);
    }
}

// Call on page load
document.addEventListener('DOMContentLoaded', loadDashboard);
```

---

## 3. ROLE-BASED ENDPOINT STRUCTURE

### Society Admin Endpoints (`admin/api.php`)
| Action | Method | Description |
|--------|--------|-------------|
| `dashboard_stats` | GET | Get society overview |
| `buildings_list` | GET | List all buildings |
| `fund_transactions` | GET | Paginated transactions |
| `recent_activity` | GET | Recent audit logs |
| `add_building` | POST | Create new building |
| `add_fund_transaction` | POST | Record fund movement |
| `create_meeting` | POST | Schedule meeting |

### Building Admin Endpoints (`building/api.php`)
| Action | Method | Description |
|--------|--------|-------------|
| `dashboard_stats` | GET | Get building overview |
| `members_list` | GET | List building members |
| `fund_balance` | GET | Get building fund |
| `add_member` | POST | Create new member |
| `add_transaction` | POST | Record transaction |
| `add_maintenance` | POST | Create maintenance entry |

### Member Endpoints (`member/api.php`)
| Action | Method | Description |
|--------|--------|-------------|
| `dashboard` | GET | Get member overview |
| `my_maintenance` | GET | My maintenance records |
| `add_note` | POST | Add personal note |

---

## 4. PRODUCTION DEPLOYMENT CHECKLIST

### Database Setup
- [ ] Run `database.sql` to create all tables
- [ ] Verify `last_login` column exists in `users` table
- [ ] Verify `activity_logs` table exists
- [ ] Create database indexes for performance:
```sql
ALTER TABLE users ADD INDEX idx_users_last_login (last_login);
ALTER TABLE society_fund ADD INDEX idx_society_fund_date (date);
ALTER TABLE building_fund ADD INDEX idx_building_fund_date (date);
ALTER TABLE maintenance ADD INDEX idx_maintenance_status (status);
```

### Security Configuration
- [ ] Change default passwords for demo accounts
- [ ] Enable HTTPS (required for production)
- [ ] Configure `session.cookie_secure = 1` in php.ini
- [ ] Configure `session.cookie_httponly = 1` in php.ini
- [ ] Set strong `session.cookie_samesite = "Strict"`
- [ ] Uncomment IP/User Agent validation in `auth.php` if users have static IPs

### File Permissions
- [ ] Set `config/db.php` to 640 (readable only by owner)
- [ ] Ensure `uploads/` directory is writable (if file uploads added)
- [ ] Set all PHP files to 644

### Error Handling
- [ ] Set `display_errors = Off` in production php.ini
- [ ] Configure error logging to file
- [ ] Check Apache/Nginx error logs regularly

### Performance Optimization
- [ ] Enable OPcache in PHP
- [ ] Configure MySQL query cache
- [ ] Add database connection pooling (if high traffic)

---

## 5. TESTING CHECKLIST

### Authentication & Security
- [ ] Login with valid credentials works
- [ ] Login with invalid credentials fails
- [ ] Inactive users cannot login
- [ ] CSRF token validation works (try submitting without token)
- [ ] Session expires after inactivity
- [ ] Direct URL access without login redirects to login
- [ ] Cross-role access is blocked (e.g., member accessing admin pages)

### Role-Based Access
- [ ] Society Admin can access all society features
- [ ] Society Pramukh has read-only access
- [ ] Building Admin can only access own building
- [ ] Member can only access own building
- [ ] Cross-building access is blocked
- [ ] Society Admin can access any building in their society

### Data Integrity
- [ ] Fund transactions update balances correctly
- [ ] Maintenance entries are building-specific
- [ ] Members are scoped to buildings
- [ ] Notes are user-specific
- [ ] Meetings show only for appropriate level (society/building)

---

## 6. BACKUP & MAINTENANCE

### Regular Backups
- [ ] Schedule daily database backups
- [ ] Keep 7 days of backup history
- [ ] Store backups off-site

### Monitoring
- [ ] Monitor `activity_logs` for suspicious activity
- [ ] Check failed login attempts in error logs
- [ ] Monitor disk space and database size

---

## 7. DEMO CREDENTIALS (Change in Production!)

| Role | Email | Password |
|------|-------|----------|
| Society Admin | admin@society.test | admin123 |
| Society Pramukh | pramukh@society.test | pramukh123 |
| Building Admin | building@society.test | building123 |
| Member | member@society.test | member123 |

---

## 8. QUICK REFERENCE

### Include in Every Protected Page
```php
<?php
require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']); // or ['building_admin'], ['member'], etc.

set_security_headers();
$societyId = require_society_scope(); // or require_building_scope()
?>
```

### CSRF in Forms
```php
<form method="post">
    <?php echo csrf_field(); ?>
    <!-- other form fields -->
</form>
```

### API Response Format
```json
{
    "success": true|false,
    "message": "Human readable message",
    "data": { ... },
    "timestamp": "2024-01-15 10:30:00"
}
```

---

## 9. TROUBLESHOOTING

### CSRF Token Mismatch
- Ensure `csrf_meta()` is in HTML `<head>`
- Check that JavaScript is reading the token correctly
- Verify session is persisting across requests

### Session Timeout Too Fast
- Check PHP `session.gc_maxlifetime` setting
- Verify cookie settings in php.ini
- Check browser cookie settings

### Database Connection Errors
- Verify `config/db.php` credentials
- Check MySQL service is running
- Ensure database `society_app` exists

### Permission Denied Errors
- Check role assignment in database
- Verify `society_id` and `building_id` are set correctly
- Check `require_role()` parameters match user role
