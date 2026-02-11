<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['building_admin']);

$buildingId = require_building_scope();

$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income_total,
    COALESCE(SUM(CASE WHEN type IN ('expense','use_money') THEN amount ELSE 0 END),0) AS expense_total
    FROM building_fund WHERE building_id = ?");
$stmt->execute([$buildingId]);
$fund = $stmt->fetch();
$fundTotal = (float)($fund['income_total'] ?? 0) - (float)($fund['expense_total'] ?? 0);

$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END),0) AS unpaid_total,
    COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS paid_total
    FROM maintenance m
    JOIN users u ON u.id = m.member_id
    WHERE u.building_id = ?");
$stmt->execute([$buildingId]);
$maint = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE building_id = ? AND role='member'");
$stmt->execute([$buildingId]);
$members = (int)($stmt->fetch()['c'] ?? 0);

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">Reports</div>
</div>

<div class="grid" style="margin-top:12px;">
    <div class="col-6">
        <div class="card">
            <div class="h1">Building Fund</div>
            <table class="table">
                <tr><th>Income</th><td><?php echo e(number_format((float)$fund['income_total'], 2, '.', '')); ?></td></tr>
                <tr><th>Expense + Use Money</th><td><?php echo e(number_format((float)$fund['expense_total'], 2, '.', '')); ?></td></tr>
                <tr><th>Total</th><td><strong><?php echo e(number_format($fundTotal, 2, '.', '')); ?></strong></td></tr>
            </table>
        </div>
    </div>

    <div class="col-6">
        <div class="card">
            <div class="h1">Maintenance Summary</div>
            <table class="table">
                <tr><th>Members</th><td><?php echo e((string)$members); ?></td></tr>
                <tr><th>Paid Total</th><td><?php echo e(number_format((float)($maint['paid_total'] ?? 0), 2, '.', '')); ?></td></tr>
                <tr><th>Unpaid Total</th><td><?php echo e(number_format((float)($maint['unpaid_total'] ?? 0), 2, '.', '')); ?></td></tr>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
