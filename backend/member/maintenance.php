<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['member']);

$user = current_user();
$memberId = (int)($user['id'] ?? 0);
$buildingId = require_building_scope();

$stmt = $pdo->prepare("SELECT amount, month, status, created_at FROM maintenance WHERE member_id = ? ORDER BY month DESC, id DESC");
$stmt->execute([$memberId]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">My Maintenance</div>
</div>

<div class="card" style="margin-top:12px;">
    <table class="table">
        <thead>
        <tr>
            <th>Created</th>
            <th>Month</th>
            <th>Amount</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo e($r['created_at']); ?></td>
                <td><?php echo e(month_label($r['month'])); ?></td>
                <td><?php echo e((string)$r['amount']); ?></td>
                <td><?php echo e($r['status']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="4" class="muted">No maintenance entries</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
