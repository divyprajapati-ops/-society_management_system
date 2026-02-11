<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']);

$societyId = require_society_scope();
$user = current_user();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim((string)($_POST['message'] ?? ''));

    if ($message === '') {
        $error = 'Message is required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, level, message) VALUES (?, 'society', ?)");
        $stmt->execute([(int)$user['id'], $message]);
        $success = 'Note added.';
        $_POST = [];
    }
}

$stmt = $pdo->prepare("SELECT n.id, n.message, n.created_at, u.name AS user_name
    FROM notes n
    JOIN users u ON u.id = n.user_id
    WHERE n.level='society' AND u.society_id = ?
    ORDER BY n.created_at DESC, n.id DESC");
$stmt->execute([$societyId]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">Society Notes</div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <form method="post" action="" style="margin-top:12px;">
        <label class="label">Add Note</label>
        <textarea name="message" required><?php echo e($_POST['message'] ?? ''); ?></textarea>
        <div style="margin-top:10px;">
            <button class="btn btn-primary" type="submit">Add</button>
        </div>
    </form>
</div>

<div class="card" style="margin-top:12px;">
    <div class="h1">All Notes</div>
    <table class="table">
        <thead>
        <tr>
            <th>Time</th>
            <th>By</th>
            <th>Message</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo e($r['created_at']); ?></td>
                <td><?php echo e($r['user_name']); ?></td>
                <td><?php echo nl2br(e($r['message'])); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="3" class="muted">No notes</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
