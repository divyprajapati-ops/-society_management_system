<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']);

$societyId = require_society_scope();

$success = null;
$error = null;

$action = (string)($_GET['action'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string)($_POST['action'] ?? '');

    if ($postAction === 'add') {
        $name = trim((string)($_POST['building_name'] ?? ''));
        if ($name === '') {
            $error = 'Building name is required.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO buildings (society_id, building_name) VALUES (?, ?)');
            $stmt->execute([$societyId, $name]);
            $success = 'Building added.';
        }
    }

    if ($postAction === 'edit') {
        $editId = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['building_name'] ?? ''));

        if ($editId <= 0 || $name === '') {
            $error = 'Invalid request.';
        } else {
            $stmt = $pdo->prepare('UPDATE buildings SET building_name = ? WHERE id = ? AND society_id = ?');
            $stmt->execute([$name, $editId, $societyId]);
            $success = 'Building updated.';
        }
    }

    if ($postAction === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        if ($deleteId <= 0) {
            $error = 'Invalid request.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM buildings WHERE id = ? AND society_id = ?');
            $stmt->execute([$deleteId, $societyId]);
            $success = 'Building deleted.';
        }
    }

    if ($postAction === 'seed13') {
        try {
            $defaults = ['A Wing','B Wing','C Wing','D Wing','E Wing','F Wing','G Wing','H Wing','I Wing','J Wing','K Wing','L Wing','M Wing'];

            $stmt = $pdo->prepare('SELECT building_name FROM buildings WHERE society_id = ?');
            $stmt->execute([$societyId]);
            $existingRows = $stmt->fetchAll();

            $existing = [];
            foreach ($existingRows as $r) {
                $existing[strtolower(trim((string)($r['building_name'] ?? '')))] = true;
            }

            $toCreate = [];
            foreach ($defaults as $d) {
                $k = strtolower(trim($d));
                if ($k !== '' && !isset($existing[$k])) {
                    $toCreate[] = $d;
                }
            }

            if (!$toCreate) {
                $success = 'All 13 default buildings already exist.';
            } else {
                $pdo->beginTransaction();
                $ins = $pdo->prepare('INSERT INTO buildings (society_id, building_name) VALUES (?, ?)');
                foreach ($toCreate as $name) {
                    $ins->execute([$societyId, $name]);
                }
                $pdo->commit();
                $success = 'Default buildings created: ' . count($toCreate);
            }
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to create default buildings.';
        }
    }

    if ($postAction === 'upsert_building_admin') {
        $buildingId = (int)($_POST['building_id'] ?? 0);
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $status = (string)($_POST['status'] ?? 'active');

        if ($buildingId <= 0) {
            $error = 'Invalid request.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Valid email is required.';
        } elseif (!in_array($status, ['active', 'inactive'], true)) {
            $error = 'Invalid status.';
        } else {
            $stmt = $pdo->prepare('SELECT id, building_name FROM buildings WHERE id = ? AND society_id = ? LIMIT 1');
            $stmt->execute([$buildingId, $societyId]);
            $building = $stmt->fetch();

            if (!$building) {
                $error = 'Building not found.';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role='building_admin' AND society_id = ? AND building_id = ? ORDER BY id ASC LIMIT 1");
                $stmt->execute([$societyId, $buildingId]);
                $existingUserId = (int)($stmt->fetch()['id'] ?? 0);

                try {
                    $name = trim((string)($building['building_name'] ?? ''));
                    $name = ($name !== '' ? $name : 'Building') . ' Admin';

                    if ($existingUserId > 0) {
                        if ($password !== '') {
                            $hash = password_hash($password, PASSWORD_BCRYPT);
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, status = ? WHERE id = ? AND role='building_admin' AND society_id = ? AND building_id = ?");
                            $stmt->execute([$name, $email, $hash, $status, $existingUserId, $societyId, $buildingId]);
                            $success = 'Building admin updated (password changed).';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, status = ? WHERE id = ? AND role='building_admin' AND society_id = ? AND building_id = ?");
                            $stmt->execute([$name, $email, $status, $existingUserId, $societyId, $buildingId]);
                            $success = 'Building admin updated.';
                        }
                    } else {
                        if ($password === '') {
                            $error = 'Password is required for new building admin.';
                        } else {
                            $hash = password_hash($password, PASSWORD_BCRYPT);
                            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, society_id, building_id, status) VALUES (?, ?, ?, 'building_admin', ?, ?, ?)");
                            $stmt->execute([$name, $email, $hash, $societyId, $buildingId, $status]);
                            $success = 'Building admin created.';
                        }
                    }
                } catch (Throwable $t) {
                    $error = 'Failed to save building admin (email may already exist).';
                }
            }
        }
    }
}

$editRow = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT id, building_name FROM buildings WHERE id = ? AND society_id = ?');
    $stmt->execute([$id, $societyId]);
    $editRow = $stmt->fetch();
}

$stmt = $pdo->prepare("SELECT b.id, b.building_name,
    u.id AS admin_user_id,
    u.email AS admin_email,
    u.status AS admin_status
    FROM buildings b
    LEFT JOIN users u ON u.id = (
        SELECT uu.id FROM users uu
        WHERE uu.role='building_admin' AND uu.society_id = b.society_id AND uu.building_id = b.id
        ORDER BY uu.id ASC LIMIT 1
    )
    WHERE b.society_id = ?
    ORDER BY b.building_name ASC");
$stmt->execute([$societyId]);
$rows = $stmt->fetchAll();

$user = current_user();
$stmt = $pdo->prepare('SELECT name, address, fund_total FROM society WHERE id = ?');
$stmt->execute([$societyId]);
$society = $stmt->fetch();

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Buildings - <?php echo e($society['name'] ?? 'Society'); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#1e3b8a",
                        "background-light": "#f8fafc",
                        "background-dark": "#121620",
                    },
                    fontFamily: {
                        display: ["Inter"],
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
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
<body class="bg-background-light dark:bg-background-dark font-display text-[#1F2937] dark:text-gray-100">
<div class="flex h-screen overflow-hidden">
    <aside class="w-64 flex-shrink-0 bg-primary text-white flex flex-col justify-between p-4 h-full">
        <div class="flex flex-col gap-8">
            <div class="flex items-center gap-3 px-2">
                <div class="bg-white/20 p-2 rounded-lg">
                    <span class="material-symbols-outlined text-white">domain</span>
                </div>
                <div class="flex flex-col">
                    <h1 class="text-white text-lg font-bold leading-tight">SocietyAdmin</h1>
                    <p class="text-white/70 text-xs font-normal"><?php echo e($society['name'] ?? ''); ?></p>
                </div>
            </div>
            <nav class="flex flex-col gap-1">
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="society_fund.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                    <span>Society Fund</span>
                </a>
                <a href="buildings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium">
                    <span class="material-symbols-outlined">apartment</span>
                    <span>Buildings</span>
                </a>
                <a href="users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">group</span>
                    <span>Users & Roles</span>
                </a>
                <a href="maintenance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">engineering</span>
                    <span>Maintenance</span>
                </a>
                <a href="meetings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">groups</span>
                    <span>Meetings</span>
                </a>
                <a href="notes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">sticky_note_2</span>
                    <span>Notes</span>
                </a>
            </nav>
        </div>
        <div class="pt-4 border-t border-white/10">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                <span class="material-symbols-outlined">logout</span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="flex items-center justify-between px-8 py-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-slate-900 sticky top-0 z-10">
            <div class="flex items-center gap-6 flex-1">
                <div>
                    <h2 class="text-[#1F2937] dark:text-white text-xl font-bold">Buildings</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($society['address'] ?? ''); ?></p>
                </div>
                <div class="max-w-md w-full relative hidden md:block">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input class="w-full bg-gray-100 dark:bg-gray-800 border-none rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-primary/20 text-sm" placeholder="Search buildings..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400 relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-gray-900"></span>
                </button>
                <div class="h-8 w-[1px] bg-gray-200 dark:bg-gray-700 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-[#1F2937] dark:text-white"><?php echo e($user['name'] ?? 'Society Admin'); ?></p>
                        <p class="text-xs text-gray-500">Society Admin</p>
                    </div>
                    <div class="relative">
                        <button onclick="toggleProfileMenu()" class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center hover:bg-primary/30 transition-colors">
                            <span class="material-symbols-outlined text-primary">person</span>
                        </button>
                        <div id="profileMenu" class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-800 hidden z-50">
                            <div class="px-4 py-3">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo e($user['name'] ?? ''); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($user['email'] ?? ''); ?></div>
                            </div>
                            <hr class="border-gray-200 dark:border-gray-800">
                            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <span class="material-symbols-outlined text-lg">logout</span>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-8 flex flex-col gap-6">
            <?php if ($error): ?>
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">error</span>
                        <span><?php echo e($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-lg">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">check_circle</span>
                        <span><?php echo e($success); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4 flex-col sm:flex-row sm:items-center mb-6">
                    <h3 class="text-lg font-bold text-[#1F2937] dark:text-white"><?php echo $editRow ? 'Edit Building' : 'Add New Building'; ?></h3>
                    <?php if (!$editRow): ?>
                        <form method="post" action="" onsubmit="return confirm('Create default buildings up to total 13?');">
                            <input type="hidden" name="action" value="seed13">
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-white/70 dark:bg-slate-900/60 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-white dark:hover:bg-slate-900 transition-colors text-sm font-semibold">
                                <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
                                Auto Create 13 Buildings
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if ($editRow): ?>
                    <form method="post" action="" class="space-y-4">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?php echo e((string)$editRow['id']); ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Building Name</label>
                            <input class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white" type="text" name="building_name" value="<?php echo e((string)$editRow['building_name']); ?>" required>
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium">Save</button>
                            <a href="buildings.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors font-medium">Cancel</a>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="post" action="" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Building Name</label>
                            <input class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white" type="text" name="building_name" placeholder="Enter building name" required>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium">Add Building</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-[#1F2937] dark:text-white">Buildings List</h3>
                    <p class="text-sm text-gray-500 mt-1">Manage society buildings</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Building</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Building Admin Login</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <?php foreach ($rows as $r): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                                <span class="material-symbols-outlined text-lg">apartment</span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e((string)$r['building_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                            $adminEmail = (string)($r['admin_email'] ?? '');
                                            $adminStatus = (string)($r['admin_status'] ?? '');
                                            $suggestedEmail = 'building' . (string)$r['id'] . '@society.test';
                                        ?>
                                        <details class="group">
                                            <summary class="list-none cursor-pointer select-none">
                                                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-slate-900/60 hover:bg-white dark:hover:bg-slate-900 transition-colors">
                                                    <span class="material-symbols-outlined text-[18px] text-gray-600 dark:text-gray-300">key</span>
                                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                                        <?php echo $adminEmail !== '' ? e($adminEmail) : 'Create Login'; ?>
                                                    </span>
                                                    <?php if ($adminEmail !== ''): ?>
                                                        <span class="text-xs px-2 py-0.5 rounded-full <?php echo ($adminStatus === 'active') ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200'; ?>">
                                                            <?php echo e($adminStatus !== '' ? $adminStatus : 'active'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="material-symbols-outlined text-[18px] text-gray-500 group-open:rotate-180 transition-transform">expand_more</span>
                                                </div>
                                            </summary>
                                            <div class="mt-3 p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30">
                                                <form method="post" action="" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                                                    <input type="hidden" name="action" value="upsert_building_admin">
                                                    <input type="hidden" name="building_id" value="<?php echo e((string)$r['id']); ?>">

                                                    <div class="md:col-span-2">
                                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Email</label>
                                                        <input class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-900 dark:text-white text-sm" type="email" name="email" value="<?php echo e($adminEmail !== '' ? $adminEmail : $suggestedEmail); ?>" required>
                                                    </div>

                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Password</label>
                                                        <input class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-900 dark:text-white text-sm" type="text" name="password" placeholder="<?php echo $adminEmail !== '' ? 'Leave blank to keep' : 'Required'; ?>" <?php echo $adminEmail !== '' ? '' : 'required'; ?>>
                                                    </div>

                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Status</label>
                                                        <select class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-900 dark:text-white text-sm" name="status" required>
                                                            <option value="active" <?php echo ($adminStatus === '' || $adminStatus === 'active') ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo ($adminStatus === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </div>

                                                    <div class="md:col-span-4 flex items-center justify-end gap-2">
                                                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors text-sm font-semibold" onclick="return confirm('Save building admin login for this building?');">
                                                            <span class="material-symbols-outlined text-[18px]">save</span>
                                                            Save Login
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </details>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="buildings.php?action=edit&id=<?php echo e((string)$r['id']); ?>" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                                <span class="material-symbols-outlined text-sm">edit</span>
                                                Edit
                                            </a>
                                            <form method="post" action="" onsubmit="return confirm('Delete this building?');" class="inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>">
                                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                                    <span class="material-symbols-outlined text-sm">delete</span>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No buildings found</td>
                                </tr>
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
</script>

</body>
</html>
