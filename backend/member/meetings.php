<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['member']);

$buildingId = require_building_scope();

$stmt = $pdo->prepare("SELECT title, meeting_date FROM meetings WHERE level='building' AND building_id = ? ORDER BY meeting_date DESC, id DESC");
$stmt->execute([$buildingId]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">Building Meetings</div>
</div>

<div class="card" style="margin-top:12px;">
    <table class="table">
        <thead>
        <tr>
            <th>Date</th>
            <th>Title</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo e($r['meeting_date']); ?></td>
                <td><?php echo e($r['title']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="2" class="muted">No meetings</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
