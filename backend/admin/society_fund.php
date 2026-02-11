<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']);

$societyId = require_society_scope();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = (string)($_POST['type'] ?? '');
    $amount = (string)($_POST['amount'] ?? '');
    $description = trim((string)($_POST['description'] ?? ''));
    $date = (string)($_POST['date'] ?? '');

    if (!in_array($type, ['income', 'expense', 'use_money'], true)) {
        $error = 'Invalid type.';
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } elseif ($date === '') {
        $error = 'Date is required.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO society_fund (society_id, amount, type, description, date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$societyId, $amount, $type, $description, $date]);

            $delta = ($type === 'income') ? (float)$amount : ((float)$amount * -1);
            $stmt = $pdo->prepare('UPDATE society SET fund_total = fund_total + ? WHERE id = ?');
            $stmt->execute([$delta, $societyId]);

            $pdo->commit();
            $success = 'Transaction added.';
            $_POST = [];
        } catch (Throwable $t) {
            $pdo->rollBack();
            $error = 'Failed to save.';
        }
    }
}

$stmt = $pdo->prepare('SELECT id, amount, type, description, date, created_at FROM society_fund WHERE society_id = ? ORDER BY date DESC, id DESC');
$stmt->execute([$societyId]);
$rows = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT fund_total FROM society WHERE id = ?');
$stmt->execute([$societyId]);
$fundTotal = $stmt->fetch()['fund_total'] ?? '0.00';

// Get society info for header
$stmt = $pdo->prepare('SELECT name, address, fund_total FROM society WHERE id = ?');
$stmt->execute([$societyId]);
$society = $stmt->fetch();

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Society Fund Management - <?php echo e($society['name'] ?? 'Society Management'); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#1e3b8a",
                        "background-light": "#f8fafc",
                        "background-dark": "#121620",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
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
<!-- Sidebar -->
<aside class="w-64 flex-shrink-0 bg-primary text-white flex flex-col justify-between p-4 h-full">
    <div class="flex flex-col gap-8">
        <div class="flex items-center gap-3 px-2">
            <div class="bg-white/20 p-2 rounded-lg">
                <span class="material-symbols-outlined text-white">domain</span>
            </div>
            <div class="flex flex-col">
                <h1 class="text-white text-lg font-bold leading-tight">SocietyAdmin</h1>
                <p class="text-white/70 text-xs font-normal">Management System</p>
            </div>
        </div>
        <nav class="flex flex-col gap-1">
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a href="society_fund.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium">
                <span class="material-symbols-outlined">account_balance_wallet</span>
                <span>Society Fund</span>
            </a>
            <a href="meetings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                <span class="material-symbols-outlined">groups</span>
                <span>Meetings</span>
            </a>
            <a href="buildings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                <span class="material-symbols-outlined">apartment</span>
                <span>Buildings</span>
            </a>
            <a href="residents.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                <span class="material-symbols-outlined">person</span>
                <span>Residents</span>
            </a>
            <a href="maintenance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                <span class="material-symbols-outlined">engineering</span>
                <span>Maintenance</span>
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
        </nav>
    </div>
</aside>

<!-- Main Content Area -->
<main class="flex-1 flex flex-col overflow-y-auto">
    <!-- Top Navigation -->
    <header class="flex items-center justify-between px-8 py-4 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-slate-900 sticky top-0 z-10">
        <div class="flex items-center gap-6 flex-1">
            <h2 class="text-[#1F2937] dark:text-white text-xl font-bold">Society Fund Management</h2>
            <div class="max-w-md w-full relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input class="w-full bg-gray-100 dark:bg-gray-800 border-none rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-primary/20 text-sm" placeholder="Search transactions..." type="text"/>
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
                    <p class="text-sm font-semibold text-[#1F2937] dark:text-white">Admin User</p>
                    <p class="text-xs text-gray-500">Super Admin</p>
                </div>
                <div class="relative">
                    <button onclick="toggleProfileMenu()" class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center hover:bg-primary/30 transition-colors">
                        <span class="material-symbols-outlined text-primary">person</span>
                    </button>
                    <div id="profileMenu" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-900 rounded-lg shadow-lg border border-gray-200 dark:border-gray-800 hidden z-50">
                        <a href="#" class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                            <span class="material-symbols-outlined text-lg">person</span>
                            Profile
                        </a>
                        <a href="#" class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                            <span class="material-symbols-outlined text-lg">settings</span>
                            Settings
                        </a>
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

    <!-- Dashboard Content -->
    <div class="p-8 flex flex-col gap-6">
        <!-- Fund Overview Card -->
        <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-[#1F2937] dark:text-white">Fund Overview</h3>
                    <p class="text-sm text-gray-500 mt-1">Manage society finances</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Current Balance</p>
                    <p class="text-3xl font-bold text-primary">₹<?php echo number_format($fundTotal, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
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

        <!-- Add Transaction Form -->
        <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold text-[#1F2937] dark:text-white mb-6">Add Transaction</h3>
            <form method="post" action="" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Transaction Type</label>
                        <select name="type" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white">
                            <option value="income" <?php echo (($_POST['type'] ?? '') === 'income') ? 'selected' : ''; ?>>Income</option>
                            <option value="expense" <?php echo (($_POST['type'] ?? '') === 'expense') ? 'selected' : ''; ?>>Expense</option>
                            <option value="use_money" <?php echo (($_POST['type'] ?? '') === 'use_money') ? 'selected' : ''; ?>>Use Money</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Amount (₹)</label>
                        <input type="number" step="0.01" min="0" name="amount" value="<?php echo e($_POST['amount'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <input type="text" name="description" value="<?php echo e($_POST['description'] ?? ''); ?>" placeholder="Enter transaction description" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date</label>
                        <input type="date" name="date" value="<?php echo e($_POST['date'] ?? date('Y-m-d')); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white">
                    </div>
                </div>
                <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium">
                    <span class="material-symbols-outlined text-sm mr-1">add</span>
                    Add Transaction
                </button>
            </form>
        </div>

        <!-- Transactions List -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-bold text-[#1F2937] dark:text-white">Recent Transactions</h3>
                <p class="text-sm text-gray-500 mt-1">Transaction history</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <?php foreach ($rows as $r): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                <?php echo date('M d, Y', strtotime($r['date'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    <?php 
                                    if ($r['type'] === 'income') echo 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                                    elseif ($r['type'] === 'expense') echo 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
                                    else echo 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
                                    ?>">
                                    <?php echo ucfirst($r['type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                ₹<?php echo number_format($r['amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                <?php echo e((string)($r['description'] ?? 'No description')); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="material-symbols-outlined text-3xl">account_balance_wallet</span>
                                    <p>No transactions found</p>
                                </div>
                            </td>
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
// Profile Menu Toggle
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('hidden');
    
    // Close menu when clicking outside
    document.addEventListener('click', function closeMenu(e) {
        if (!e.target.closest('.relative')) {
            menu.classList.add('hidden');
            document.removeEventListener('click', closeMenu);
        }
    });
}

// Search functionality
const searchInput = document.querySelector('input[type="text"]');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        // Implement search functionality here
        console.log('Searching for:', searchTerm);
    });
}

// Add hover effects to cards
document.querySelectorAll('.glass-card, .bg-white, .dark\\:bg-slate-900').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.transition = 'all 0.3s ease';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});
</script>

</body>
</html>
