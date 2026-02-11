<?php
/**
 * Stitch UI Integration Example - Society Admin API
 * 
 * This file demonstrates how to connect Stitch-generated UI to the PHP backend.
 * Copy and adapt this pattern for your specific endpoints.
 */

require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']);

// Set security headers and JSON response
set_security_headers();
header('Content-Type: application/json; charset=utf-8');

$societyId = require_society_scope();
$user = current_user();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($pdo, $societyId, $action);
            break;
            
        case 'POST':
            // Validate CSRF for state-changing operations
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!validate_csrf_token($csrfToken)) {
                json_error('Invalid CSRF token', 403);
            }
            handlePostRequest($pdo, $societyId, $user, $action);
            break;
            
        default:
            json_error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    error_log("[SocietyApp] API Error: " . $e->getMessage());
    json_error('Internal server error', 500);
}

// GET Request Handler
function handleGetRequest(PDO $pdo, int $societyId, string $action): never {
    switch ($action) {
        case 'dashboard_stats':
            // Fetch dashboard statistics
            $stats = [
                'buildings' => 0,
                'residents' => 0,
                'meetings' => 0,
                'fund_balance' => 0
            ];
            
            // Buildings count
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM buildings WHERE society_id = ?');
            $stmt->execute([$societyId]);
            $stats['buildings'] = (int)$stmt->fetchColumn();
            
            // Residents count (actual members with login history)
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE society_id = ? AND role = "member" AND last_login IS NOT NULL');
            $stmt->execute([$societyId]);
            $stats['residents'] = (int)$stmt->fetchColumn();
            
            // Meetings count (upcoming)
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM meetings WHERE society_id = ? AND meeting_date >= CURDATE()');
            $stmt->execute([$societyId]);
            $stats['meetings'] = (int)$stmt->fetchColumn();
            
            // Fund balance
            $stmt = $pdo->prepare('SELECT fund_total FROM society WHERE id = ?');
            $stmt->execute([$societyId]);
            $stats['fund_balance'] = (float)$stmt->fetchColumn();
            
            json_success('Dashboard stats retrieved', $stats);
            
        case 'buildings_list':
            $stmt = $pdo->prepare('
                SELECT b.id, b.building_name, 
                       COUNT(DISTINCT u.id) as member_count,
                       COUNT(DISTINCT CASE WHEN u.last_login IS NOT NULL THEN u.id END) as active_members
                FROM buildings b
                LEFT JOIN users u ON u.building_id = b.id AND u.role = "member"
                WHERE b.society_id = ?
                GROUP BY b.id
                ORDER BY b.building_name ASC
            ');
            $stmt->execute([$societyId]);
            $buildings = $stmt->fetchAll();
            
            json_success('Buildings retrieved', ['buildings' => $buildings]);
            
        case 'fund_transactions':
            $page = input_int($_GET['page'] ?? 1, 1);
            $result = paginate(
                $pdo,
                'SELECT id, amount, type, description, date, created_at FROM society_fund WHERE society_id = ? ORDER BY date DESC, id DESC',
                [$societyId],
                $page,
                20
            );
            
            json_success('Transactions retrieved', $result);
            
        case 'recent_activity':
            $stmt = $pdo->prepare('
                SELECT a.action, a.details, a.created_at, u.name as user_name
                FROM activity_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE u.society_id = ? OR a.user_id IS NULL
                ORDER BY a.created_at DESC
                LIMIT 10
            ');
            $stmt->execute([$societyId]);
            $activities = $stmt->fetchAll();
            
            json_success('Recent activity retrieved', ['activities' => $activities]);
            
        default:
            json_error('Unknown action', 400);
    }
}

// POST Request Handler
function handlePostRequest(PDO $pdo, int $societyId, array $user, string $action): never {
    switch ($action) {
        case 'add_building':
            $name = input_string($_POST['building_name'] ?? '', 150);
            
            if ($name === '') {
                json_error('Building name is required');
            }
            
            try {
                $stmt = $pdo->prepare('INSERT INTO buildings (society_id, building_name) VALUES (?, ?)');
                $stmt->execute([$societyId, $name]);
                $buildingId = (int)$pdo->lastInsertId();
                
                log_activity($pdo, 'building_created', "Created building '{$name}' (ID: {$buildingId})");
                
                json_success('Building created successfully', ['building_id' => $buildingId]);
            } catch (PDOException $e) {
                json_error('Failed to create building: ' . $e->getMessage());
            }
            
        case 'add_fund_transaction':
            $amount = input_string($_POST['amount'] ?? '');
            $type = input_string($_POST['type'] ?? ''); // 'income', 'expense', 'use_money'
            $description = input_string($_POST['description'] ?? '', 255);
            $date = input_string($_POST['date'] ?? '');
            
            // Validation
            if (!is_numeric($amount) || $amount <= 0) {
                json_error('Valid amount is required');
            }
            if (!in_array($type, ['income', 'expense', 'use_money'], true)) {
                json_error('Invalid transaction type');
            }
            if ($date === '' || !strtotime($date)) {
                $date = date('Y-m-d');
            }
            
            try {
                $pdo->beginTransaction();
                
                // Insert transaction
                $stmt = $pdo->prepare('
                    INSERT INTO society_fund (society_id, amount, type, description, date)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([$societyId, $amount, $type, $description, $date]);
                
                // Update society fund total
                $adjustment = ($type === 'income') ? $amount : -$amount;
                $stmt = $pdo->prepare('UPDATE society SET fund_total = fund_total + ? WHERE id = ?');
                $stmt->execute([$adjustment, $societyId]);
                
                $pdo->commit();
                
                log_activity($pdo, 'fund_transaction', "Added {$type} of {$amount}");
                
                json_success('Transaction recorded successfully');
            } catch (PDOException $e) {
                $pdo->rollBack();
                json_error('Failed to record transaction: ' . $e->getMessage());
            }
            
        case 'create_meeting':
            $title = input_string($_POST['title'] ?? '', 200);
            $meetingDate = input_string($_POST['meeting_date'] ?? '');
            $level = input_string($_POST['level'] ?? 'society'); // 'society' or 'building'
            $buildingId = input_int($_POST['building_id'] ?? 0);
            
            if ($title === '') {
                json_error('Meeting title is required');
            }
            if (!strtotime($meetingDate)) {
                json_error('Valid meeting date is required');
            }
            
            // If building-level meeting, verify ownership
            if ($level === 'building' && $buildingId > 0) {
                if (!verify_building_ownership($pdo, $buildingId)) {
                    json_error('Access denied to this building', 403);
                }
            }
            
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO meetings (level, society_id, building_id, title, meeting_date)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $level,
                    $societyId,
                    $level === 'building' ? $buildingId : null,
                    $title,
                    $meetingDate
                ]);
                
                log_activity($pdo, 'meeting_created', "Created meeting '{$title}' for {$meetingDate}");
                
                json_success('Meeting created successfully');
            } catch (PDOException $e) {
                json_error('Failed to create meeting: ' . $e->getMessage());
            }
            
        default:
            json_error('Unknown action', 400);
    }
}
