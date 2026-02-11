<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']);

$societyId = require_society_scope();

$success = null;
$error = null;

$stmt = $pdo->prepare('SELECT id, building_name FROM buildings WHERE society_id = ? ORDER BY building_name ASC');
$stmt->execute([$societyId]);
$buildings = $stmt->fetchAll();

$action = (string)($_GET['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['action'] ?? '');

    if ($postAction === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? '');
        $status = (string)($_POST['status'] ?? 'active');
        $buildingId = (int)($_POST['building_id'] ?? 0);

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Name, email, password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email.';
        } elseif (!in_array($role, ['society_admin','society_pramukh','building_admin'], true)) {
            $error = 'Invalid role.';
        } elseif (!in_array($status, ['active','inactive'], true)) {
            $error = 'Invalid status.';
        } else {
            if (in_array($role, ['building_admin','member'], true) && $buildingId <= 0) {
                $error = 'Building is required for building roles.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                try {
                    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, society_id, building_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $email, $hash, $role, $societyId, ($buildingId > 0 ? $buildingId : null), $status]);
                    $success = 'User created.';
                    $_POST = [];
                } catch (Throwable $t) {
                    $error = 'Failed to create user (email may already exist).';
                }
            }
        }
    }

    if ($postAction === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $role = (string)($_POST['role'] ?? '');
        $status = (string)($_POST['status'] ?? 'active');
        $buildingId = (int)($_POST['building_id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($id <= 0 || $name === '') {
            $error = 'Invalid request.';
        } elseif (!in_array($role, ['society_admin','society_pramukh','building_admin'], true)) {
            $error = 'Invalid role.';
        } elseif (!in_array($status, ['active','inactive'], true)) {
            $error = 'Invalid status.';
        } else {
            if (in_array($role, ['building_admin','member'], true) && $buildingId <= 0) {
                $error = 'Building is required for building roles.';
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, status = ?, building_id = ? WHERE id = ? AND society_id = ? AND role <> 'member'");
                    $stmt->execute([$name, $role, $status, ($buildingId > 0 ? $buildingId : null), $id, $societyId]);

                    if ($newPassword !== '') {
                        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND society_id = ? AND role <> 'member'");
                        $stmt->execute([$hash, $id, $societyId]);
                    }

                    $pdo->commit();
                    $success = 'User updated.';
                } catch (Throwable $t) {
                    $pdo->rollBack();
                    $error = 'Failed to update.';
                }
            }
        }
    }
}

$editRow = null;
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT id, name, email, role, building_id, status FROM users WHERE id = ? AND society_id = ? AND role <> 'member'");
    $stmt->execute([$editId, $societyId]);
    $editRow = $stmt->fetch();

    if (!$editRow) {
        $error = 'Not allowed.';
    }
}

$stmt = $pdo->prepare('SELECT u.id, u.name, u.email, u.role, u.status, b.building_name
    FROM users u
    LEFT JOIN buildings b ON b.id = u.building_id
    WHERE u.society_id = ?
    ORDER BY u.id DESC');
$stmt->execute([$societyId]);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="card">
    <div class="h1">Users & Role Assignment</div>
    <div class="muted">Passwords are never shown. Member emails are hidden in the list.</div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($editRow): ?>
        <form method="post" action="" style="margin-top:12px;">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo e((string)$editRow['id']); ?>">

            <div class="grid">
                <div class="col-6">
                    <label class="label">Name</label>
                    <input class="input" type="text" name="name" value="<?php echo e($editRow['name']); ?>" required>
                </div>
                <div class="col-6">
                    <label class="label">Email (read-only)</label>
                    <input class="input" type="text" value="<?php echo e($editRow['email']); ?>" disabled>
                </div>
                <div class="col-6">
                    <label class="label">Role</label>
                    <select name="role" required>
                        <?php foreach (['society_admin','society_pramukh','building_admin'] as $r): ?>
                            <option value="<?php echo e($r); ?>" <?php echo ($editRow['role'] === $r) ? 'selected' : ''; ?>><?php echo e($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="label">Status</label>
                    <select name="status" required>
                        <option value="active" <?php echo ($editRow['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($editRow['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="label">Building (for building roles)</label>
                    <select name="building_id">
                        <option value="0">-- None --</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?php echo e((string)$b['id']); ?>" <?php echo ((int)$editRow['building_id'] === (int)$b['id']) ? 'selected' : ''; ?>><?php echo e($b['building_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="label">Reset Password (optional)</label>
                    <input class="input" type="password" name="new_password" placeholder="Leave blank to keep unchanged">
                </div>
                <div class="col-12" style="display:flex; gap:10px;">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <a class="btn" href="users.php">Cancel</a>
                </div>
            </div>
        </form>
    <?php else: ?>
        <form method="post" action="" style="margin-top:12px;">
            <input type="hidden" name="action" value="create">
            <div class="grid">
                <div class="col-6">
                    <label class="label">Name</label>
                    <input class="input" type="text" name="name" value="<?php echo e($_POST['name'] ?? ''); ?>" required>
                </div>
                <div class="col-6">
                    <label class="label">Email</label>
                    <input class="input" type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="col-6">
                    <label class="label">Password</label>
                    <input class="input" type="password" name="password" required>
                </div>
                <div class="col-6">
                    <label class="label">Role</label>
                    <select name="role" required>
                        <?php foreach (['society_pramukh','building_admin'] as $r): ?>
                            <option value="<?php echo e($r); ?>" <?php echo (($_POST['role'] ?? '') === $r) ? 'selected' : ''; ?>><?php echo e($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="label">Building (for building roles)</label>
                    <select name="building_id">
                        <option value="0">-- None --</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?php echo e((string)$b['id']); ?>" <?php echo (($_POST['building_id'] ?? '') == $b['id']) ? 'selected' : ''; ?>><?php echo e($b['building_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="label">Status</label>
                    <select name="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Create User</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:12px;">
    <div class="h1">User List</div>

    <table class="table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Building</th>
            <th>Status</th>
            <th style="width:120px;">Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo e((string)$r['id']); ?></td>
                <td><?php echo e($r['name']); ?></td>
                <td>
                    <?php if ($r['role'] === 'member'): ?>
                        <span class="muted">Hidden</span>
                    <?php else: ?>
                        <?php echo e($r['email']); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo e($r['role']); ?></td>
                <td><?php echo e((string)($r['building_name'] ?? '')); ?></td>
                <td><?php echo e($r['status']); ?></td>
                <td>
                    <?php if ($r['role'] === 'member'): ?>
                        <span class="muted">Restricted</span>
                    <?php else: ?>
                        <a class="btn" href="users.php?action=edit&id=<?php echo e((string)$r['id']); ?>">Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="7" class="muted">No users</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
