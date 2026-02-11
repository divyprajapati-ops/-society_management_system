<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['member']);

$buildingId = require_building_scope();

$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income_total,
    COALESCE(SUM(CASE WHEN type IN ('expense','use_money') THEN amount ELSE 0 END),0) AS expense_total
    FROM building_fund WHERE building_id = ?");
$stmt->execute([$buildingId]);
$totals = $stmt->fetch();
$fundTotal = (float)($totals['income_total'] ?? 0) - (float)($totals['expense_total'] ?? 0);

$stmt = $pdo->prepare('SELECT amount, type, date FROM building_fund WHERE building_id = ? ORDER BY date DESC, id DESC');
$stmt->execute([$buildingId]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">Building Fund (View Only)</div>
    <div class="muted">Current total: <strong><?php echo e(number_format($fundTotal, 2, '.', '')); ?></strong></div>
</div>

<div class="card" style="margin-top:12px;">
    <table class="table">
        <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo e($r['date']); ?></td>
                <td><?php echo e($r['type']); ?></td>
                <td><?php echo e((string)$r['amount']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="3" class="muted">No transactions</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
