<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']);

$societyId = require_society_scope();

$stmt = $pdo->prepare("SELECT m.id, m.amount, m.month, m.status, m.created_at,
    u.name AS member_name,
    b.building_name
    FROM maintenance m
    JOIN users u ON u.id = m.member_id
    LEFT JOIN buildings b ON b.id = u.building_id
    WHERE u.society_id = ?
    ORDER BY m.created_at DESC, m.id DESC");
$stmt->execute([$societyId]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">All Building Maintenance</div>
    <div class="muted">View-only (created by building admins)</div>
</div>

<div class="card" style="margin-top:12px;">
    <table class="table">
        <thead>
        <tr>
            <th>Created</th>
            <th>Building</th>
            <th>Member</th>
            <th>Month</th>
            <th>Amount</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo e($r['created_at']); ?></td>
                <td><?php echo e((string)($r['building_name'] ?? '')); ?></td>
                <td><?php echo e($r['member_name']); ?></td>
                <td><?php echo e(month_label($r['month'])); ?></td>
                <td><?php echo e((string)$r['amount']); ?></td>
                <td><?php echo e($r['status']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="muted">No maintenance entries</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
