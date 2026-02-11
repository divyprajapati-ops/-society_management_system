    <?php

require_once __DIR__ . '/../config/auth.php';
require_role(['building_admin']);

$buildingId = require_building_scope();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = (string)($_POST['type'] ?? '');
    $amount = (string)($_POST['amount'] ?? '');
    $date = (string)($_POST['date'] ?? '');

    if (!in_array($type, ['income','expense','use_money'], true)) {
        $error = 'Invalid type.';
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } elseif ($date === '') {
        $error = 'Date is required.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO building_fund (building_id, amount, type, date) VALUES (?, ?, ?, ?)');
        $success = 'Transaction added.';
        $_POST = [];
    }
}

$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income_total,
        COALESCE(SUM(CASE WHEN type IN ('expense','use_money') THEN amount ELSE 0 END),0) AS expense_total
    FROM building_fund WHERE building_id = ?
");
$stmt->execute([$buildingId]);
$totals = $stmt->fetch();
$fundTotal = (float)($totals['income_total'] ?? 0) - (float)($totals['expense_total'] ?? 0);

$stmt = $pdo->prepare('SELECT amount, type, date, created_at FROM building_fund WHERE building_id = ? ORDER BY date DESC, id DESC');
$stmt->execute([$buildingId]);
$rows = $stmt->fetchAll();

$user = current_user();

$stmt = $pdo->prepare('SELECT building_name FROM buildings WHERE id = ?');
$stmt->execute([$buildingId]);
$building = $stmt->fetch();

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Building Fund - <?php echo e($building['building_name'] ?? 'Building'); ?></title>
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
                    <h1 class="text-white text-lg font-bold">BuildingAdmin</h1>
                    <p class="text-white/70 text-xs font-normal"><?php echo e($building['building_name'] ?? ''); ?></p>
                </div>
            </div>
            <nav class="flex flex-col gap-1">
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="fund.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                    <span>Building Fund</span>
                </a>
                <a href="meetings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">groups</span>
                    <span>Meetings</span>
                </a>
                <a href="members.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">person</span>
                    <span>Members</span>
                </a>
                <a href="maintenance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">engineering</span>
                    <span>Maintenance</span>
                </a>
                <a href="reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                    <span class="material-symbols-outlined">analytics</span>
                    <span>Reports</span>
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
                    <h2 class="text-[#0f172a] dark:text-white text-xl font-bold">Building Fund</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Manage building finances</p>
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
                        <p class="text-sm font-semibold text-[#0f172a] dark:text-white"><?php echo e($user['name'] ?? 'Building Admin'); ?></p>
                        <p class="text-xs text-gray-500">Building Admin</p>
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

            <!-- Fund Balance Card -->
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <span class="material-symbols-outlined text-2xl">account_balance_wallet</span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Current Balance</p>
                            <p class="text-3xl font-bold text-[#0f172a] dark:text-white">₹<?php echo number_format($fundTotal, 2); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-emerald-600 dark:text-emerald-400 font-medium">Income: ₹<?php echo number_format((float)($totals['income_total'] ?? 0), 2); ?></span>
                            <span class="text-gray-300">|</span>
                            <span class="text-rose-600 dark:text-rose-400 font-medium">Expense: ₹<?php echo number_format((float)($totals['expense_total'] ?? 0), 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Transaction Form -->
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-bold text-[#0f172a] dark:text-white mb-4">Add Transaction</h3>
                <form method="post" action="" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type</label>
                            <select name="type" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white">
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                                <option value="use_money">Use Money</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (₹)</label>
                            <input type="number" step="0.01" min="0" name="amount" value="<?php echo e($_POST['amount'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date</label>
                            <input type="date" name="date" value="<?php echo e($_POST['date'] ?? date('Y-m-d')); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                            Add Transaction
                        </button>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-[#0f172a] dark:text-white">Recent Transactions</h3>
                    <p class="text-sm text-gray-500 mt-1">History of all fund movements</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Amount</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Added</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <?php foreach ($rows as $r): ?>
                                <?php
                                $typeBadge = '';
                                $typeClass = '';
                                switch ($r['type']) {
                                    case 'income':
                                        $typeBadge = 'Income';
                                        $typeClass = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200';
                                        break;
                                    case 'expense':
                                        $typeBadge = 'Expense';
                                        $typeClass = 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200';
                                        break;
                                    case 'use_money':
                                        $typeBadge = 'Use Money';
                                        $typeClass = 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200';
                                        break;
                                }
                                ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white"><?php echo e($r['date']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo e($typeClass); ?>">
                                            <?php echo e($typeBadge); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white text-right font-medium">₹<?php echo e(number_format((float)$r['amount'], 2)); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo e(date('M d, Y', strtotime($r['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No transactions found</td>
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
