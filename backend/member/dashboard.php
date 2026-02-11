<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['member']);

$buildingId = require_building_scope();
$user = current_user();

$stmt = $pdo->prepare('SELECT building_name FROM buildings WHERE id = ?');
$stmt->execute([$buildingId]);
$buildingName = $stmt->fetch()['building_name'] ?? '';

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">Member Dashboard</div>
    <div class="muted">Welcome, <?php echo e($user['name'] ?? ''); ?> (<?php echo e($buildingName); ?>)</div>
</div>

<div class="card" style="margin-top:12px;">
    <div class="h1">Quick Links</div>
    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">
        <a class="btn" href="fund.php">Building Fund</a>
        <a class="btn" href="meetings.php">Meetings</a>
        <a class="btn" href="maintenance.php">My Maintenance</a>
        <a class="btn" href="notes.php">Notes</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
