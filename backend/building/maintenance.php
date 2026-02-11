<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['building_admin']);

$buildingId = require_building_scope();

$success = null;
$error = null;

$stmt = $pdo->prepare("SELECT id, name FROM users WHERE building_id = ? AND role='member' AND status='active' ORDER BY name ASC");
$stmt->execute([$buildingId]);
$members = $stmt->fetchAll();

$selectedMonth = (string)($_REQUEST['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$viewMonth = $selectedMonth;
$monthYear = substr($selectedMonth, 0, 4);
if (!preg_match('/^\d{4}$/', $monthYear)) {
    $monthYear = date('Y');
    $selectedMonth = $monthYear . '-' . date('m');
}

$monthOptions = [];
for ($m = 1; $m <= 12; $m++) {
    $val = $monthYear . '-' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
    $monthOptions[] = ['value' => $val, 'label' => month_label($val)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'bulk_add') {
        $memberIds = $_POST['member_ids'] ?? [];
        $month = trim((string)($_POST['month'] ?? ''));
        $amount = (string)($_POST['amount'] ?? '');
        $status = (string)($_POST['status'] ?? 'unpaid');

        if (!is_array($memberIds) || count($memberIds) === 0) {
            $error = 'Select at least 1 member.';
        } elseif ($month === '' || strlen($month) !== 7) {
            $error = 'Month (YYYY-MM) is required.';
        } elseif (!is_numeric($amount) || (float)$amount <= 0) {
            $error = 'Amount must be greater than 0.';
        } elseif (!in_array($status, ['paid','unpaid'], true)) {
            $error = 'Invalid status.';
        } else {
            $cleanIds = [];
            foreach ($memberIds as $mid) {
                $mid = (int)$mid;
                if ($mid > 0) {
                    $cleanIds[] = $mid;
                }
            }
            $cleanIds = array_values(array_unique($cleanIds));

            if (count($cleanIds) === 0) {
                $error = 'Select at least 1 member.';
            } else {
                $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
                $params = $cleanIds;
                $params[] = $buildingId;

                $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND building_id = ? AND role='member' AND status='active'");
                $stmt->execute($params);
                $valid = $stmt->fetchAll();
                $validIds = array_map(static fn($r) => (int)$r['id'], $valid);

                if (count($validIds) === 0) {
                    $error = 'No valid members selected.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $ins = $pdo->prepare('INSERT INTO maintenance (member_id, amount, month, status) VALUES (?, ?, ?, ?)');
                        $created = 0;
                        foreach ($validIds as $vid) {
                            $ins->execute([$vid, $amount, $month, $status]);
                            $created++;
                        }
                        $pdo->commit();
                        $success = 'Maintenance added for selected members: ' . $created;
                        $_POST = [];
                    } catch (Throwable $t) {
                        $pdo->rollBack();
                        $error = 'Failed to add bulk maintenance.';
                    }
                }
            }
        }
    }

    if ($action === 'add') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $month = trim((string)($_POST['month'] ?? ''));
        $amount = (string)($_POST['amount'] ?? '');
        $status = (string)($_POST['status'] ?? 'unpaid');

        if ($memberId <= 0 || $month === '' || strlen($month) !== 7) {
            $error = 'Member and month (YYYY-MM) are required.';
        } elseif (!is_numeric($amount) || (float)$amount <= 0) {
            $error = 'Amount must be greater than 0.';
        } elseif (!in_array($status, ['paid','unpaid'], true)) {
            $error = 'Invalid status.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND building_id = ? AND role='member' LIMIT 1");
            $stmt->execute([$memberId, $buildingId]);
            $ok = $stmt->fetch();

            if (!$ok) {
                $error = 'Invalid member.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO maintenance (member_id, amount, month, status) VALUES (?, ?, ?, ?)');
                $stmt->execute([$memberId, $amount, $month, $status]);
                $success = 'Maintenance added.';
                $_POST = [];
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = (string)($_POST['status'] ?? 'unpaid');

        if ($id <= 0 || !in_array($newStatus, ['paid','unpaid'], true)) {
            $error = 'Invalid request.';
        } else {
            $stmt = $pdo->prepare("UPDATE maintenance m
                JOIN users u ON u.id = m.member_id
                SET m.status = ?
                WHERE m.id = ? AND u.building_id = ?");
            $stmt->execute([$newStatus, $id, $buildingId]);
            $success = 'Status updated.';
        }
    }
}

$stmt = $pdo->prepare("SELECT m.id, m.amount, m.month, m.status, m.created_at, u.name AS member_name
    FROM maintenance m
    JOIN users u ON u.id = m.member_id
    WHERE u.building_id = ? AND m.month = ?
    ORDER BY m.created_at DESC, m.id DESC");
$stmt->execute([$buildingId, $viewMonth]);
$rows = $stmt->fetchAll();

$user = current_user();

$stmt = $pdo->prepare('SELECT building_name FROM buildings WHERE id = ?');
$stmt->execute([$buildingId]);
$building = $stmt->fetch();

$paidCount = 0;
$unpaidCount = 0;
$totalAmount = 0;
$collectedAmount = 0;
foreach ($rows as $r) {
    $totalAmount += (float)$r['amount'];
    if ($r['status'] === 'paid') {
        $paidCount++;
        $collectedAmount += (float)$r['amount'];
    } else {
        $unpaidCount++;
    }
}

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Building Maintenance - <?php echo e($building['building_name'] ?? 'Building'); ?></title>
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
        .checkbox-grid {
            max-height: 200px;
            overflow-y: auto;
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
                <a href="members.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">person</span><span>Members</span></a>
                <a href="maintenance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium"><span class="material-symbols-outlined">engineering</span><span>Maintenance</span></a>
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
                    <h2 class="text-[#0f172a] dark:text-white text-xl font-bold">Building Maintenance</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Manage monthly maintenance fees</p>
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Total Entries</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo count($rows); ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400"><span class="material-symbols-outlined">receipt_long</span></div>
                    </div>
                </div>
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Paid</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo $paidCount; ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400"><span class="material-symbols-outlined">check_circle</span></div>
                    </div>
                </div>
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Unpaid</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo $unpaidCount; ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center text-rose-600 dark:text-rose-400"><span class="material-symbols-outlined">pending</span></div>
                    </div>
                </div>
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Collected</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white">₹<?php echo number_format($collectedAmount, 2); ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400"><span class="material-symbols-outlined">payments</span></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Bulk Add Form -->
                <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-[#0f172a] dark:text-white mb-4 flex items-center gap-2"><span class="material-symbols-outlined">group_add</span>Bulk Maintenance</h3>
                    <form method="post" action="" class="space-y-4">
                        <input type="hidden" name="action" value="bulk_add">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Month</label><select name="month" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white"><?php foreach ($monthOptions as $opt): ?><option value="<?php echo e($opt['value']); ?>" <?php echo ($selectedMonth === $opt['value']) ? 'selected' : ''; ?>><?php echo e($opt['label']); ?></option><?php endforeach; ?></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (₹)</label><input type="number" step="0.01" min="0" name="amount" value="<?php echo e($_POST['amount'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white" placeholder="0.00"></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label><select name="status" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white"><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select></div>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Members</label>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 checkbox-grid">
                                <label class="flex items-center gap-2 mb-2 pb-2 border-b border-gray-200 dark:border-gray-700"><input type="checkbox" id="selectAllBulk" class="rounded border-gray-300 text-primary focus:ring-primary"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">Select All</span></label>
                                <div class="grid grid-cols-2 gap-2"><?php foreach ($members as $m): ?><label class="flex items-center gap-2"><input type="checkbox" name="member_ids[]" value="<?php echo e((string)$m['id']); ?>" class="member-checkbox rounded border-gray-300 text-primary focus:ring-primary"><span class="text-sm text-gray-600 dark:text-gray-400"><?php echo e($m['name']); ?></span></label><?php endforeach; ?></div>
                            </div>
                        </div>
                        <div class="flex justify-end"><button type="submit" onclick="return confirm('Add maintenance for selected members?');" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium"><span class="material-symbols-outlined text-[18px]">add</span>Add Bulk</button></div>
                    </form>
                </div>

                <!-- Single Add Form -->
                <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-[#0f172a] dark:text-white mb-4 flex items-center gap-2"><span class="material-symbols-outlined">person_add</span>Single Entry</h3>
                    <form method="post" action="" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Member</label><select name="member_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white"><option value="">-- Select --</option><?php foreach ($members as $m): ?><option value="<?php echo e((string)$m['id']); ?>" <?php echo (($_POST['member_id'] ?? '') == $m['id']) ? 'selected' : ''; ?>><?php echo e($m['name']); ?></option><?php endforeach; ?></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Month</label><select name="month" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white"><?php foreach ($monthOptions as $opt): ?><option value="<?php echo e($opt['value']); ?>" <?php echo ($selectedMonth === $opt['value']) ? 'selected' : ''; ?>><?php echo e($opt['label']); ?></option><?php endforeach; ?></select></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (₹)</label><input type="number" step="0.01" min="0" name="amount" value="<?php echo e($_POST['amount'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white" placeholder="0.00"></div>
                            <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label><select name="status" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white"><option value="unpaid">Unpaid</option><option value="paid">Paid</option></select></div>
                        </div>
                        <div class="flex justify-end"><button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium"><span class="material-symbols-outlined text-[18px]">add</span>Add Entry</button></div>
                    </form>
                </div>
            </div>

            <!-- Maintenance List -->
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div><h3 class="text-lg font-bold text-[#0f172a] dark:text-white">Maintenance Records</h3><p class="text-sm text-gray-500 mt-1">For <?php echo e(month_label($viewMonth)); ?></p></div>
                    <form method="get" action="" class="flex items-center gap-2">
                        <select name="month" required class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white text-sm"><?php foreach ($monthOptions as $opt): ?><option value="<?php echo e($opt['value']); ?>" <?php echo ($viewMonth === $opt['value']) ? 'selected' : ''; ?>><?php echo e($opt['label']); ?></option><?php endforeach; ?></select>
                        <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-sm font-medium">Filter</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Member</th><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Month</th><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Amount</th><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th><th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Action</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <?php foreach ($rows as $r): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white font-medium"><?php echo e($r['member_name']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo e(month_label($r['month'])); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white text-right">₹<?php echo e(number_format((float)$r['amount'], 2)); ?></td>
                                    <td class="px-6 py-4"><span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $r['status'] === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200'; ?>"><?php echo e($r['status']); ?></span></td>
                                    <td class="px-6 py-4 text-right">
                                        <form method="post" action="" class="inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>"><input type="hidden" name="status" value="<?php echo ($r['status'] === 'paid') ? 'unpaid' : 'paid'; ?>"><input type="hidden" name="month" value="<?php echo e($viewMonth); ?>"><button type="submit" class="px-3 py-1.5 text-xs font-medium <?php echo $r['status'] === 'paid' ? 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'; ?> rounded transition-colors"><?php echo ($r['status'] === 'paid') ? 'Mark Unpaid' : 'Mark Paid'; ?></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?><tr><td colspan="5" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No maintenance records for this month</td></tr><?php endif; ?>
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
// Select all checkbox for bulk
document.getElementById('selectAllBulk')?.addEventListener('change', function() {
    document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>

</body>
</html>
