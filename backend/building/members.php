<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['building_admin']);

$buildingId = require_building_scope();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'seed20') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT society_id FROM buildings WHERE id = ? LIMIT 1');
            $stmt->execute([$buildingId]);
            $societyId = (int)($stmt->fetch()['society_id'] ?? 0);

            if ($societyId <= 0) {
                throw new RuntimeException('Invalid building');
            }

            $hash = password_hash('member123', PASSWORD_BCRYPT);
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, society_id, building_id, status)
                VALUES (?, ?, ?, 'member', ?, ?, 'active')");

            $created = 0;
            for ($i = 1; $i <= 20; $i++) {
                $name = 'Demo Member ' . $i;
                $email = 'demo' . $i . '.b' . $buildingId . '@society.test';
                try {
                    $insert->execute([$name, $email, $hash, $societyId, $buildingId]);
                    $created++;
                } catch (Throwable $t) {
                    // ignore duplicates
                }
            }

            $pdo->commit();
            $success = 'Demo members created: ' . $created;
        } catch (Throwable $t) {
            $pdo->rollBack();
            $error = 'Failed to create demo members.';
        }
    }

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $status = (string)($_POST['status'] ?? 'active');

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Name, email, password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email.';
        } elseif (!in_array($status, ['active','inactive'], true)) {
            $error = 'Invalid status.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, society_id, building_id, status)
                    VALUES (?, ?, ?, 'member', (SELECT society_id FROM buildings WHERE id = ?), ?, ?)");
                $stmt->execute([$name, $email, $hash, $buildingId, $buildingId, $status]);
                $success = 'Member added.';
                $_POST = [];
            } catch (Throwable $t) {
                $error = 'Failed to add (email may already exist).';
            }
        }
    }

    if ($action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'active');

        if ($id <= 0 || !in_array($status, ['active','inactive'], true)) {
            $error = 'Invalid request.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND building_id = ? AND role='member'");
            $stmt->execute([$status, $id, $buildingId]);
            $success = 'Status updated.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $error = 'Invalid request.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND building_id = ? AND role='member'");
            $stmt->execute([$id, $buildingId]);
            if ($stmt->rowCount() > 0) {
                $success = 'Member deleted.';
            } else {
                $error = 'Delete not allowed.';
            }
        }
    }

    if ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($id <= 0 || $newPassword === '') {
            $error = 'Member and new password are required.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND building_id = ? AND role='member'");
                $stmt->execute([$hash, $id, $buildingId]);
                if ($stmt->rowCount() > 0) {
                    $success = 'Password reset successful.';
                } else {
                    $error = 'Reset not allowed.';
                }
            } catch (Throwable $t) {
                $error = 'Failed to reset password.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT id, name, email, status, created_at FROM users WHERE building_id = ? AND role='member' ORDER BY id DESC");
$stmt->execute([$buildingId]);
$rows = $stmt->fetchAll();

$user = current_user();

$stmt = $pdo->prepare('SELECT building_name FROM buildings WHERE id = ?');
$stmt->execute([$buildingId]);
$building = $stmt->fetch();

$activeCount = 0;
$inactiveCount = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'active') $activeCount++;
    else $inactiveCount++;
}

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Building Members - <?php echo e($building['building_name'] ?? 'Building'); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#4f46e5",
                        "background-light": "#f8fafc",
                        "background-dark": "#121620",
                    },
                    fontFamily: {
                        display: ["Inter"],
                    },
                },
            },
        };
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .dark .glass-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#0f172a] dark:text-gray-100">
<div class="flex h-screen overflow-hidden">
    <aside class="w-64 flex-shrink-0 bg-primary text-white flex flex-col justify-between p-4 h-full">
        <div class="flex flex-col gap-8">
            <div class="flex items-center gap-3 px-2">
                <div class="bg-white/20 p-2 rounded-lg">
                    <span class="material-symbols-outlined text-white">apartment</span>
                </div>
                <div class="flex flex-col">
                    <h1 class="text-white text-lg font-bold leading-tight">BuildingAdmin</h1>
                    <p class="text-white/70 text-xs font-normal"><?php echo e($building['building_name'] ?? ''); ?></p>
                </div>
            </div>
            <nav class="flex flex-col gap-1">
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
                <a href="fund.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">account_balance_wallet</span><span>Building Fund</span></a>
                <a href="meetings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">groups</span><span>Meetings</span></a>
                <a href="members.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium"><span class="material-symbols-outlined">person</span><span>Members</span></a>
                <a href="maintenance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">engineering</span><span>Maintenance</span></a>
                <a href="reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
                <a href="notes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">sticky_note_2</span><span>Notes</span></a>
            </nav>
        </div>
        <div class="pt-4 border-t border-white/10">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="flex items-center justify-between px-8 py-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-slate-900 sticky top-0 z-10">
            <div class="flex items-center gap-6 flex-1">
                <div>
                    <h2 class="text-[#0f172a] dark:text-white text-xl font-bold">Building Members</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Manage building residents</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400 relative"><span class="material-symbols-outlined">notifications</span><span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-gray-900"></span></button>
                <div class="h-8 w-[1px] bg-gray-200 dark:bg-gray-700 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-[#0f172a] dark:text-white"><?php echo e($user['name'] ?? 'Building Admin'); ?></p>
                        <p class="text-xs text-gray-500">Building Admin</p>
                    </div>
                    <div class="relative">
                        <button onclick="toggleProfileMenu()" class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center hover:bg-primary/30 transition-colors"><span class="material-symbols-outlined text-primary">person</span></button>
                        <div id="profileMenu" class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-800 hidden z-50">
                            <div class="px-4 py-3"><div class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo e($user['name'] ?? ''); ?></div><div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($user['email'] ?? ''); ?></div></div>
                            <hr class="border-gray-200 dark:border-gray-800">
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"><span class="material-symbols-outlined text-lg">logout</span>Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-8 flex flex-col gap-6">
            <?php if ($error): ?>
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg"><div class="flex items-center gap-2"><span class="material-symbols-outlined text-lg">error</span><span><?php echo e($error); ?></span></div></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-lg"><div class="flex items-center gap-2"><span class="material-symbols-outlined text-lg">check_circle</span><span><?php echo e($success); ?></span></div></div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Total Members</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo count($rows); ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400"><span class="material-symbols-outlined">group</span></div>
                    </div>
                </div>
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Active</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo $activeCount; ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400"><span class="material-symbols-outlined">check_circle</span></div>
                    </div>
                </div>
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Inactive</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo $inactiveCount; ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center text-rose-600 dark:text-rose-400"><span class="material-symbols-outlined">block</span></div>
                    </div>
                </div>
            </div>

            <!-- Demo Members -->
            <div class="glass-card rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-800">
                <form method="post" action="" class="flex items-center gap-3">
                    <input type="hidden" name="action" value="seed20">
                    <button type="submit" onclick="return confirm('Create 20 demo members with password member123?');" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors font-medium text-sm">
                        <span class="material-symbols-outlined text-[18px]">person_add</span>
                        Create 20 Demo Members
                    </button>
                    <span class="text-xs text-gray-500">Default password: <strong>member123</strong></span>
                </form>
            </div>

            <!-- Add Member Form -->
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-bold text-[#0f172a] dark:text-white mb-4">Add New Member</h3>
                <form method="post" action="" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Name</label><input type="text" name="name" value="<?php echo e($_POST['name'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white" placeholder="Enter member name"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label><input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white" placeholder="email@example.com"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label><input type="password" name="password" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white" placeholder="Set password"></div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label><select name="status" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                    </div>
                    <div class="flex justify-end"><button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium"><span class="material-symbols-outlined text-[18px]">person_add</span>Add Member</button></div>
                </form>
            </div>

            <!-- Members Table -->
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-[#0f172a] dark:text-white">Member List</h3>
                    <p class="text-sm text-gray-500 mt-1">All building members</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">ID</th><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <?php foreach ($rows as $r): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white"><?php echo e((string)$r['id']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white font-medium"><?php echo e($r['name']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo e($r['email']); ?></td>
                                    <td class="px-6 py-4"><span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $r['status'] === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200'; ?>"><?php echo e($r['status']); ?></span></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="post" action="" class="inline"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>"><input type="hidden" name="status" value="<?php echo ($r['status'] === 'active') ? 'inactive' : 'active'; ?>"><button type="submit" class="px-2 py-1 text-xs font-medium <?php echo $r['status'] === 'active' ? 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'; ?> rounded transition-colors"><?php echo ($r['status'] === 'active') ? 'Deactivate' : 'Activate'; ?></button></form>
                                            <button onclick="document.getElementById('resetModal<?php echo $r['id']; ?>').classList.remove('hidden')" class="px-2 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition-colors">Reset</button>
                                            <form method="post" action="" onsubmit="return confirm('Delete this member?');" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>"><button type="submit" class="px-2 py-1 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors">Delete</button></form>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Reset Password Modal -->
                                <div id="resetModal<?php echo $r['id']; ?>" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                                    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl max-w-md w-full p-6">
                                        <h4 class="text-lg font-bold text-[#0f172a] dark:text-white mb-4">Reset Password - <?php echo e($r['name']); ?></h4>
                                        <form method="post" action="" class="space-y-4">
                                            <input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>">
                                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label><input type="text" name="new_password" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white" placeholder="Enter new password"></div>
                                            <div class="flex justify-end gap-3">
                                                <button type="button" onclick="document.getElementById('resetModal<?php echo $r['id']; ?>').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">Cancel</button>
                                                <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">Reset Password</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?>
                                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No members found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('hidden');
    document.addEventListener('click', function closeMenu(e) {
        if (!e.target.closest('.relative')) {
            menu.classList.add('hidden');
            document.removeEventListener('click', closeMenu);
        }
    });
}
// Close modal on backdrop click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('fixed') && e.target.classList.contains('bg-black/50')) {
        e.target.classList.add('hidden');
    }
});
</script>

</body>
</html>
