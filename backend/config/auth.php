<?php

require_once __DIR__ . '/db.php';

// Security: Regenerate session ID periodically to prevent fixation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    // Regenerate session ID every 30 minutes or on privilege change
    if (!isset($_SESSION['last_regenerate']) || time() - $_SESSION['last_regenerate'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regenerate'] = time();
    }
}

// CSRF Token Functions
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function csrf_meta(): string {
    $token = generate_csrf_token();
    return '<meta name="csrf-token" content="' . e($token) . '">';
}

// API Response Helpers
function json_response(bool $success, string $message, array $data = [], int $httpCode = 200): never {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_THROW_ON_ERROR);
    exit;
}

function json_success(string $message, array $data = []): never {
    json_response(true, $message, $data, 200);
}

function json_error(string $message, int $httpCode = 400, array $data = []): never {
    json_response(false, $message, $data, $httpCode);
}

// Security Headers
function set_security_headers(): void {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    // Prevent MIME sniffing
    header('X-Content-Type-Options: nosniff');
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function app_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    
    // Detect if we're running from PHP dev server (localhost:8080)
    // or from XAMPP (localhost/divy1/society-app)
    if (str_contains($scriptName, '/backend/')) {
        // We're in the backend folder structure
        $backendPos = strpos($scriptName, '/backend/');
        if ($backendPos !== false) {
            $base = substr($scriptName, 0, $backendPos);
        }
    }
    
    // Fallback to the original society-app detection
    if ($base === null) {
        $appFolder = '/society-app';
        $pos = strpos($scriptName, $appFolder . '/');
        if ($pos === false) {
            $pos = strpos($scriptName, $appFolder);
        }

        if ($pos !== false) {
            $prefix = substr($scriptName, 0, $pos);
            $base = rtrim($prefix, '/') . $appFolder;
        } else {
            $base = rtrim(dirname($scriptName), '/');
        }
    }

    return $base ?: '';
}

function redirect_to(string $path): void
{
    $path = ltrim($path, '/');
    header('Location: ' . app_base_url() . '/' . $path);
    exit;
}

// Enhanced is_logged_in with session validation
function is_logged_in(): bool
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return false;
    }

    $user = $_SESSION['user'];
    $id = (int)($user['id'] ?? 0);
    $role = (string)($user['role'] ?? '');
    $status = (string)($user['status'] ?? 'active');
    $ip = $_SESSION['ip'] ?? '';
    $userAgent = $_SESSION['user_agent'] ?? '';

    // Basic validation
    if ($id <= 0 || $role === '' || $status !== 'active') {
        return false;
    }

    // Session binding: IP and User Agent validation (optional but secure)
    // Comment out if users have dynamic IPs
    // if ($ip !== ($_SERVER['REMOTE_ADDR'] ?? '') || $userAgent !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    //     return false;
    // }

    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        if (is_ajax_request()) {
            json_error('Authentication required', 401);
        }
        redirect_to('backend/auth/login.php');
        exit;
    }
}

function is_ajax_request(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

// Enhanced role guard with logging
function require_role(array $allowedRoles): void
{
    require_login();

    $user = current_user();
    $role = $user['role'] ?? '';

    if (!in_array($role, $allowedRoles, true)) {
        // Log unauthorized access attempt
        error_log("[SocietyApp] Unauthorized access attempt: User {$user['id']} with role '{$role}' tried to access page requiring: " . implode(', ', $allowedRoles));
        
        if (is_ajax_request()) {
            json_error('Access denied: Insufficient permissions', 403);
        }
        redirect_by_role();
        exit;
    }
}

// Enhanced scope validation with ownership checks
function require_society_scope(): int
{
    require_login();
    $user = current_user();
    $societyId = (int)($user['society_id'] ?? 0);

    if ($societyId <= 0) {
        error_log("[SocietyApp] Invalid society scope for user {$user['id']}");
        if (is_ajax_request()) {
            json_error('Invalid society scope', 403);
        }
        redirect_to('backend/auth/logout.php');
    }

    // Validate society ownership for non-admins
    $role = $user['role'] ?? '';
    if ($role !== 'society_admin' && $role !== 'society_pramukh') {
        if (is_ajax_request()) {
            json_error('Society access denied', 403);
        }
        redirect_to('backend/auth/logout.php');
    }

    return $societyId;
}

function require_building_scope(): int
{
    require_login();
    $user = current_user();
    $buildingId = (int)($user['building_id'] ?? 0);

    if ($buildingId <= 0) {
        error_log("[SocietyApp] Invalid building scope for user {$user['id']}");
        if (is_ajax_request()) {
            json_error('Invalid building scope', 403);
        }
        redirect_to('backend/auth/logout.php');
    }

    // Building admins and members must have valid building assignment
    $role = $user['role'] ?? '';
    if (!in_array($role, ['building_admin', 'member'], true)) {
        if (is_ajax_request()) {
            json_error('Building access denied', 403);
        }
        redirect_to('backend/auth/logout.php');
    }

    return $buildingId;
}

// Verify resource ownership
function verify_society_ownership(PDO $pdo, int $resourceSocietyId): bool
{
    $user = current_user();
    if (!$user) return false;
    
    $userSocietyId = (int)($user['society_id'] ?? 0);
    $role = $user['role'] ?? '';
    
    // Society admins and pramukh can access their society
    if (in_array($role, ['society_admin', 'society_pramukh'], true)) {
        return $userSocietyId === $resourceSocietyId;
    }
    
    return false;
}

function verify_building_ownership(PDO $pdo, int $resourceBuildingId): bool
{
    $user = current_user();
    if (!$user) return false;
    
    $userBuildingId = (int)($user['building_id'] ?? 0);
    $role = $user['role'] ?? '';
    
    // Building admins and members can access their building
    if (in_array($role, ['building_admin', 'member'], true)) {
        return $userBuildingId === $resourceBuildingId;
    }
    
    // Society admins can access any building in their society
    if ($role === 'society_admin' || $role === 'society_pramukh') {
        $societyId = (int)($user['society_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM buildings WHERE id = ? AND society_id = ?');
        $stmt->execute([$resourceBuildingId, $societyId]);
        return (int)$stmt->fetchColumn() > 0;
    }
    
    return false;
}

function redirect_by_role(): void
{
    if (!is_logged_in()) {
        redirect_to('backend/auth/login.php');
    }

    $role = $_SESSION['user']['role'] ?? '';

    if ($role === 'society_admin') {
        $societyId = (int)($_SESSION['user']['society_id'] ?? 0);
        if ($societyId <= 0) {
            redirect_to('backend/auth/logout.php');
        }
        redirect_to('backend/admin/dashboard.php');
        exit;
    }

    if ($role === 'society_pramukh') {
        $societyId = (int)($_SESSION['user']['society_id'] ?? 0);
        if ($societyId <= 0) {
            redirect_to('backend/auth/logout.php');
        }
        redirect_to('backend/pramukh/dashboard.php');
        exit;
    }

    if ($role === 'building_admin') {
        $buildingId = (int)($_SESSION['user']['building_id'] ?? 0);
        if ($buildingId <= 0) {
            redirect_to('backend/auth/logout.php');
        }
        redirect_to('backend/building/dashboard.php');
        exit;
    }

    if ($role === 'member') {
        $buildingId = (int)($_SESSION['user']['building_id'] ?? 0);
        if ($buildingId <= 0) {
            redirect_to('backend/auth/logout.php');
        }
        redirect_to('backend/member/dashboard.php');
        exit;
    }

    redirect_to('backend/auth/logout.php');
    exit;
}

// Output escaping
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Utility functions
function month_label(string $ym): string
{
    $dt = DateTime::createFromFormat('Y-m', $ym);
    if ($dt instanceof DateTime) {
        return $dt->format('F Y');
    }
    return $ym;
}

// Validate and sanitize input
function input_string(?string $value, int $maxLength = 255): string
{
    $value = trim((string)$value);
    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

function input_int($value, int $default = 0): int
{
    $int = filter_var($value, FILTER_VALIDATE_INT);
    return $int !== false ? $int : $default;
}

function input_email(?string $value): string
{
    $email = filter_var(trim((string)$value), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

// Pagination helper
function paginate(PDO $pdo, string $query, array $params, int $page = 1, int $perPage = 20): array
{
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    
    // Count total
    $countQuery = preg_replace('/SELECT.*?FROM/i', 'SELECT COUNT(*) FROM', $query, 1);
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    
    // Get data
    $query .= " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
        'has_more' => ($offset + count($data)) < $total
    ];
}

// Log activity
function log_activity(PDO $pdo, string $action, string $details = ''): void
{
    $user = current_user();
    $userId = $user ? (int)$user['id'] : 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Throwable $e) {
        // Silently fail - don't break user flow for logging
        error_log("[SocietyApp] Failed to log activity: " . $e->getMessage());
    }
}
