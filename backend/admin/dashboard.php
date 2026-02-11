<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']);

$societyId = require_society_scope();

$stmt = $pdo->prepare('SELECT id, name, address, fund_total FROM society WHERE id = ?');
$stmt->execute([$societyId]);
$society = $stmt->fetch();

$counts = [
    'buildings' => 0,
    'users' => 0,
    'meetings' => 0,
    'maintenance' => 0,
];

$stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM buildings WHERE society_id = ?');
$stmt->execute([$societyId]);
$counts['buildings'] = (int)($stmt->fetch()['c'] ?? 0);

$stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM users WHERE society_id = ?');
$stmt->execute([$societyId]);
$counts['users'] = (int)($stmt->fetch()['c'] ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM meetings WHERE level='society' AND society_id = ?");
$stmt->execute([$societyId]);
$counts['meetings'] = (int)($stmt->fetch()['c'] ?? 0);

// Get maintenance count for this society
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS c FROM maintenance m 
    JOIN users u ON m.member_id = u.id 
    WHERE u.society_id = ?
");
$stmt->execute([$societyId]);
$counts['maintenance'] = (int)($stmt->fetch()['c'] ?? 0);

// Get fund statistics
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
        SUM(CASE WHEN type = 'use_money' THEN amount ELSE 0 END) as total_used
    FROM society_fund 
    WHERE society_id = ? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
");
$stmt->execute([$societyId]);
$fundStats = $stmt->fetch();

// Get recent activities
$stmt = $pdo->prepare("
    (SELECT 'maintenance' as type, m.created_at as date, CONCAT('Maintenance Payment - ', m.month, ' (', u.name, ')') as title, 
            CASE WHEN m.status = 'paid' THEN 'Success' ELSE 'Pending' END as status,
            m.amount as amount
     FROM maintenance m 
     JOIN users u ON m.member_id = u.id 
     WHERE u.society_id = ? ORDER BY m.created_at DESC LIMIT 2)
    UNION ALL
    (SELECT 'meeting' as type, m.created_at as date, m.title as title, 'Scheduled' as status, 
            NULL as amount
     FROM meetings m WHERE m.society_id = ? ORDER BY m.created_at DESC LIMIT 2)
    UNION ALL
    (SELECT 'fund' as type, sf.created_at as date, CONCAT(sf.type, ' - ', sf.description) as title, 
            'Completed' as status, sf.amount as amount
     FROM society_fund sf WHERE sf.society_id = ? ORDER BY sf.created_at DESC LIMIT 2)
    ORDER BY date DESC LIMIT 6
");
$stmt->execute([$societyId, $societyId, $societyId]);
$recentActivities = $stmt->fetchAll();

// Get monthly data for chart
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(date, '%b') as month,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
    FROM society_fund 
    WHERE society_id = ? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%b'), MONTH(date)
    ORDER BY MONTH(date)
");
$stmt->execute([$societyId]);
$monthlyData = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Society Admin Dashboard - <?php echo e($society['name'] ?? 'Society Management'); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium" href="dashboard.php">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors" href="fund.php">
                <span class="material-symbols-outlined">account_balance_wallet</span>
                <span>Society Fund</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors" href="meetings.php">
                <span class="material-symbols-outlined">groups</span>
                <span>Meetings</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors" href="buildings.php">
                <span class="material-symbols-outlined">apartment</span>
                <span>Buildings</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors" href="residents.php">
                <span class="material-symbols-outlined">person</span>
                <span>Residents</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors" href="maintenance.php">
                <span class="material-symbols-outlined">engineering</span>
                <span>Maintenance</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors" href="settings.php">
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
            <h2 class="text-[#1F2937] dark:text-white text-xl font-bold">Dashboard Overview</h2>
            <div class="max-w-md w-full relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input class="w-full bg-gray-100 dark:bg-gray-800 border-none rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-primary/20 text-sm" placeholder="Search transactions, residents..." type="text"/>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button onclick="window.location.href='meetings.php?action=new'" class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors text-sm font-medium">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                <span class="hidden sm:inline">New Meeting</span>
            </button>
            <button class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400 relative">
                <span class="material-symbols-outlined">notifications</span>
                <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-gray-900"></span>
            </button>
            <button class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400">
                <span class="material-symbols-outlined">chat_bubble</span>
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
    <div class="p-8 flex flex-col gap-8">
        <!-- Society Info Section -->
        <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-[#1F2937] dark:text-white"><?php echo e($society['name'] ?? 'Society Name'); ?></h3>
                    <p class="text-sm text-gray-500 mt-1"><?php echo e($society['address'] ?? 'Society Address'); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Total Fund Balance</p>
                    <p class="text-2xl font-bold text-primary">₹<?php echo number_format($society['fund_total'] ?? 0, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- KPI Stats Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 flex flex-col gap-2">
                <div class="flex justify-between items-start">
                    <p class="text-gray-500 text-sm font-medium">Total Buildings</p>
                    <span class="material-symbols-outlined text-blue-600 bg-blue-50 p-2 rounded-lg">corporate_fare</span>
                </div>
                <p class="text-[#1F2937] dark:text-white text-2xl font-bold"><?php echo $counts['buildings']; ?></p>
                <div class="flex items-center gap-1 text-gray-500 text-sm">
                    <span class="material-symbols-outlined text-sm">apartment</span>
                    <span>Properties managed</span>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 flex flex-col gap-2">
                <div class="flex justify-between items-start">
                    <p class="text-gray-500 text-sm font-medium">Total Residents</p>
                    <span class="material-symbols-outlined text-purple-600 bg-purple-50 p-2 rounded-lg">groups</span>
                </div>
                <p class="text-[#1F2937] dark:text-white text-2xl font-bold"><?php echo $counts['users']; ?></p>
                <div class="flex items-center gap-1 text-gray-500 text-sm">
                    <span class="material-symbols-outlined text-sm">person</span>
                    <span>Active members</span>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 flex flex-col gap-2">
                <div class="flex justify-between items-start">
                    <p class="text-gray-500 text-sm font-medium">Society Meetings</p>
                    <span class="material-symbols-outlined text-emerald-600 bg-emerald-50 p-2 rounded-lg">event_available</span>
                </div>
                <p class="text-[#1F2937] dark:text-white text-2xl font-bold"><?php echo $counts['meetings']; ?></p>
                <div class="flex items-center gap-1 text-emerald-600 text-sm">
                    <span class="material-symbols-outlined text-sm">calendar_month</span>
                    <span>Scheduled events</span>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 flex flex-col gap-2">
                <div class="flex justify-between items-start">
                    <p class="text-gray-500 text-sm font-medium">Maintenance Tasks</p>
                    <span class="material-symbols-outlined text-amber-600 bg-amber-50 p-2 rounded-lg">engineering</span>
                </div>
                <p class="text-[#1F2937] dark:text-white text-2xl font-bold"><?php echo $counts['maintenance']; ?></p>
                <div class="flex items-center gap-1 text-amber-600 text-sm">
                    <span class="material-symbols-outlined text-sm">build</span>
                    <span>Active tasks</span>
                </div>
            </div>
        </div>

        <!-- Financial Overview Chart -->
        <div class="bg-white dark:bg-slate-900 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="text-lg font-bold text-[#1F2937] dark:text-white">Income vs Expense</h3>
                    <p class="text-sm text-gray-500">Financial overview for the last 6 months</p>
                </div>
                <div class="flex gap-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Income</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Expense</span>
                    </div>
                </div>
            </div>
            <div class="h-[300px] relative">
                <canvas id="financialChart"></canvas>
            </div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 flex flex-col gap-2">
                <div class="flex justify-between items-start">
                    <p class="text-gray-500 text-sm font-medium">Total Income</p>
                    <span class="material-symbols-outlined text-emerald-600 bg-emerald-50 p-2 rounded-lg">trending_up</span>
                </div>
                <p class="text-[#1F2937] dark:text-white text-2xl font-bold">₹<?php echo number_format($fundStats['total_income'] ?? 0, 2); ?></p>
                <div class="flex items-center gap-1 text-emerald-600 text-sm">
                    <span class="material-symbols-outlined text-sm">arrow_upward</span>
                    <span>Last 6 months</span>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 flex flex-col gap-2">
                <div class="flex justify-between items-start">
                    <p class="text-gray-500 text-sm font-medium">Total Expense</p>
                    <span class="material-symbols-outlined text-amber-600 bg-amber-50 p-2 rounded-lg">payments</span>
                </div>
                <p class="text-[#1F2937] dark:text-white text-2xl font-bold">₹<?php echo number_format($fundStats['total_expense'] ?? 0, 2); ?></p>
                <div class="flex items-center gap-1 text-amber-600 text-sm">
                    <span class="material-symbols-outlined text-sm">arrow_downward</span>
                    <span>Last 6 months</span>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 flex flex-col gap-2">
                <div class="flex justify-between items-start">
                    <p class="text-gray-500 text-sm font-medium">Fund Used</p>
                    <span class="material-symbols-outlined text-blue-600 bg-blue-50 p-2 rounded-lg">account_balance_wallet</span>
                </div>
                <p class="text-[#1F2937] dark:text-white text-2xl font-bold">₹<?php echo number_format($fundStats['total_used'] ?? 0, 2); ?></p>
                <div class="flex items-center gap-1 text-blue-600 text-sm">
                    <span class="material-symbols-outlined text-sm">sync</span>
                    <span>Operations</span>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 flex flex-col gap-2">
                <div class="flex justify-between items-start">
                    <p class="text-gray-500 text-sm font-medium">Active Users</p>
                    <span class="material-symbols-outlined text-purple-600 bg-purple-50 p-2 rounded-lg">groups</span>
                </div>
                <p class="text-[#1F2937] dark:text-white text-2xl font-bold"><?php echo $counts['users']; ?></p>
                <div class="flex items-center gap-1 text-purple-600 text-sm">
                    <span class="material-symbols-outlined text-sm">person</span>
                    <span>Members</span>
                </div>
            </div>
        </div>

        <!-- Recent Activity Table -->
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#1F2937] dark:text-white">Recent Activity</h3>
                <a href="#" class="text-primary text-sm font-semibold hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 sticky top-0">
                        <tr>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Activity</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <?php if (empty($recentActivities)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="material-symbols-outlined text-3xl">inbox</span>
                                    <p>No recent activity found</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $activity): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-<?php 
                                            echo $activity['type'] === 'maintenance' ? 'amber' : 
                                                 ($activity['type'] === 'meeting' ? 'blue' : 'emerald'); 
                                        ?>-100 dark:bg-<?php 
                                            echo $activity['type'] === 'maintenance' ? 'amber' : 
                                                 ($activity['type'] === 'meeting' ? 'blue' : 'emerald'); 
                                        ?>-900/30 flex items-center justify-center text-<?php 
                                            echo $activity['type'] === 'maintenance' ? 'amber' : 
                                                 ($activity['type'] === 'meeting' ? 'blue' : 'emerald'); 
                                        ?>-600">
                                            <span class="material-symbols-outlined text-lg">
                                                <?php 
                                                echo $activity['type'] === 'maintenance' ? 'engineering' : 
                                                     ($activity['type'] === 'meeting' ? 'meeting_room' : 'account_balance'); 
                                                ?>
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-[#1F2937] dark:text-white"><?php echo e($activity['title']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo ucfirst($activity['type']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo $activity['amount'] ? '₹' . number_format($activity['amount'], 2) : '-'; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($activity['date'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full bg-<?php echo $activity['status'] === 'Success' ? 'emerald' : ($activity['status'] === 'Pending' ? 'amber' : 'blue'); ?>-100 text-<?php echo $activity['status'] === 'Success' ? 'emerald' : ($activity['status'] === 'Pending' ? 'amber' : 'blue'); ?>-700 text-xs font-bold">
                                        <?php echo e($activity['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button class="text-gray-400 hover:text-primary">
                                        <span class="material-symbols-outlined">more_vert</span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
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

// Chart.js Financial Chart
const ctx = document.getElementById('financialChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthlyData); ?>;

const chart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => d.month || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'][Math.floor(Math.random() * 6)]),
        datasets: [{
            label: 'Income',
            data: monthlyData.map(d => parseFloat(d.income || 0)),
            backgroundColor: 'rgba(16, 185, 129, 0.8)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1,
            borderRadius: 4
        }, {
            label: 'Expense',
            data: monthlyData.map(d => parseFloat(d.expense || 0)),
            backgroundColor: 'rgba(251, 191, 36, 0.8)',
            borderColor: 'rgba(251, 191, 36, 1)',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 1,
                displayColors: true,
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString('en-IN');
                    }
                }
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#6B7280'
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    color: '#6B7280',
                    callback: function(value) {
                        return '₹' + value.toLocaleString('en-IN');
                    }
                }
            }
        }
    }
});

// Interactive Sidebar Navigation
document.querySelectorAll('aside nav a').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!this.classList.contains('bg-white/10')) {
            e.preventDefault();
            // Remove active class from all links
            document.querySelectorAll('aside nav a').forEach(l => {
                l.classList.remove('bg-white/10', 'text-white');
                l.classList.add('text-white/80');
            });
            // Add active class to clicked link
            this.classList.add('bg-white/10', 'text-white');
            this.classList.remove('text-white/80');
        }
    });
});

// Search functionality
const searchInput = document.querySelector('input[type="text"]');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        // Implement search functionality here
        console.log('Searching for:', searchTerm);
    });
}

// Notification badge animation
const notificationBtn = document.querySelector('button:has(.material-symbols-outlined[alt="notifications"])');
if (notificationBtn) {
    setInterval(() => {
        const badge = notificationBtn.querySelector('.bg-red-500');
        if (badge) {
            badge.classList.toggle('animate-pulse');
        }
    }, 3000);
}

// Dark mode toggle (optional enhancement)
function toggleDarkMode() {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
}

// Load saved dark mode preference
if (localStorage.getItem('darkMode') === 'true') {
    document.documentElement.classList.add('dark');
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

// Activity table row click handlers
document.querySelectorAll('tbody tr').forEach(row => {
    row.addEventListener('click', function(e) {
        if (!e.target.closest('button')) {
            // Add click animation
            this.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
            setTimeout(() => {
                this.style.backgroundColor = '';
            }, 300);
        }
    });
});

// Auto-refresh data every 30 seconds (optional)
setInterval(() => {
    // Implement auto-refresh logic here if needed
    console.log('Auto-refreshing dashboard data...');
}, 30000);
</script>

</body>
</html>
