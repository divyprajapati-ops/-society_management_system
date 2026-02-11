<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_pramukh']);

$societyId = require_society_scope();

$stmt = $pdo->prepare('SELECT fund_total FROM society WHERE id = ?');
$stmt->execute([$societyId]);
$fundTotal = $stmt->fetch()['fund_total'] ?? '0.00';

$stmt = $pdo->prepare('SELECT amount, type, description, date FROM society_fund WHERE society_id = ? ORDER BY date DESC, id DESC');
$stmt->execute([$societyId]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">Society Fund (View Only)</div>
    <div class="muted">Current total: <strong><?php echo e((string)$fundTotal); ?></strong></div>
</div>

<div class="card" style="margin-top:12px;">
    <table class="table">
        <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Description</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo e($r['date']); ?></td>
                <td><?php echo e($r['type']); ?></td>
                <td><?php echo e((string)$r['amount']); ?></td>
                <td><?php echo e((string)($r['description'] ?? '')); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="4" class="muted">No transactions</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
