<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_pramukh']);

$societyId = require_society_scope();

$stmt = $pdo->prepare('SELECT name, address, fund_total FROM society WHERE id = ?');
$stmt->execute([$societyId]);
$society = $stmt->fetch();

// Get additional statistics
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

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS c FROM maintenance m 
    JOIN users u ON m.member_id = u.id 
    WHERE u.society_id = ?
");
$stmt->execute([$societyId]);
$counts['maintenance'] = (int)($stmt->fetch()['c'] ?? 0);

// Get recent activities
$stmt = $pdo->prepare("
    (SELECT 'maintenance' as type, m.created_at as date, CONCAT('Maintenance - ', m.month) as title, 
            CASE WHEN m.status = 'paid' THEN 'Success' ELSE 'Pending' END as status
     FROM maintenance m 
     JOIN users u ON m.member_id = u.id 
     WHERE u.society_id = ? ORDER BY m.created_at DESC LIMIT 2)
    UNION ALL
    (SELECT 'meeting' as type, m.created_at as date, m.title as title, 'Scheduled' as status
     FROM meetings m WHERE m.society_id = ? ORDER BY m.created_at DESC LIMIT 2)
    ORDER BY date DESC LIMIT 4
");
$stmt->execute([$societyId, $societyId]);
$recentActivities = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Society Pramukh Dashboard - <?php echo e($society['name'] ?? 'Society Management'); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0d968b",
                        "background-light": "#f6f8f8",
                        "background-dark": "#102220",
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
            background: rgba(16, 34, 32, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">
<div class="flex h-screen overflow-hidden">
<!-- Sidebar Navigation -->
<aside class="w-64 bg-primary text-white flex flex-col h-full border-r border-primary/20">
    <div class="p-6 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-lg">
            <span class="material-symbols-outlined text-white text-2xl">apartment</span>
        </div>
        <div>
            <h1 class="text-lg font-bold leading-none">Pramukh</h1>
            <p class="text-white/70 text-xs mt-1">Building Admin</p>
        </div>
    </div>
    <nav class="flex-1 px-4 py-4 space-y-1">
        <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white">
            <span class="material-symbols-outlined text-[22px]">dashboard</span>
            <span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="society_fund.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/5 hover:text-white transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-[22px]">account_balance_wallet</span>
            <span class="text-sm font-medium">Society Fund</span>
        </a>
        <a href="meetings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/5 hover:text-white transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-[22px]">group</span>
            <span class="text-sm font-medium">Meetings</span>
        </a>
        <a href="maintenance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/5 hover:text-white transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-[22px]">handyman</span>
            <span class="text-sm font-medium">Maintenance</span>
        </a>
        <a href="notes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/5 hover:text-white transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-[22px]">note_alt</span>
            <span class="text-sm font-medium">Notes</span>
        </a>
    </nav>
    <div class="p-4 border-t border-white/10">
        <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/70 hover:bg-white/5 hover:text-white transition-colors cursor-pointer">
            <span class="material-symbols-outlined text-[22px]">settings</span>
            <span class="text-sm font-medium">Settings</span>
        </div>
        <div class="mt-4 flex items-center gap-3 px-3">
            <div class="size-9 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold border border-white/30">
                <span class="material-symbols-outlined text-sm">person</span>
            </div>
            <div class="flex flex-col overflow-hidden">
                <p class="text-sm font-medium truncate">Pramukh Admin</p>
                <p class="text-xs text-white/60">View-only Access</p>
            </div>
        </div>
        <a href="../auth/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-400 hover:bg-white/5 hover:text-red-300 transition-colors mt-2">
            <span class="material-symbols-outlined text-[22px]">logout</span>
            <span class="text-sm font-medium">Logout</span>
        </a>
    </div>
</aside>

<!-- Main Content Area -->
<main class="flex-1 flex flex-col overflow-y-auto">
    <!-- Header -->
    <header class="flex items-center justify-between bg-white dark:bg-background-dark border-b border-slate-200 dark:border-slate-800 px-8 py-4 sticky top-0 z-10">
        <div class="flex items-center gap-6">
            <div class="flex flex-col">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?php echo e($society['name'] ?? 'Society Name'); ?></h2>
                <p class="text-xs text-slate-500"><?php echo e($society['address'] ?? 'Society Address'); ?></p>
            </div>
            <div class="h-8 w-[1px] bg-slate-200 dark:bg-slate-700 mx-2"></div>
            <div class="relative w-64">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
                <input class="w-full pl-10 pr-4 py-2 bg-slate-100 dark:bg-slate-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary" placeholder="Search activities..." type="text"/>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg relative">
                <span class="material-symbols-outlined">notifications</span>
                <span class="absolute top-2 right-2 size-2 bg-red-500 rounded-full border-2 border-white dark:border-background-dark"></span>
            </button>
            <button class="p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">
                <span class="material-symbols-outlined">help_outline</span>
            </button>
            <div class="size-10 rounded-full bg-primary/20 flex items-center justify-center border border-slate-200">
                <span class="material-symbols-outlined text-primary">person</span>
            </div>
        </div>
    </header>

    <div class="p-8 space-y-6 max-w-7xl mx-auto w-full">
        <!-- Society Info Card -->
        <div class="glass-card rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">Society Overview</h3>
                    <p class="text-sm text-slate-500 mt-1">View-only access to society management</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-slate-500">Total Fund Balance</p>
                    <p class="text-2xl font-bold text-primary">₹<?php echo number_format($society['fund_total'] ?? 0, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass-card rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium text-slate-500">Total Buildings</p>
                        <span class="material-symbols-outlined text-indigo-500 bg-indigo-50 p-1.5 rounded-lg text-lg">apartment</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $counts['buildings']; ?></p>
                </div>
                <p class="text-primary text-xs font-semibold mt-4 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">domain</span> Properties
                </p>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium text-slate-500">Total Members</p>
                        <span class="material-symbols-outlined text-primary bg-primary/10 p-1.5 rounded-lg text-lg">groups</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $counts['users']; ?></p>
                </div>
                <p class="text-slate-500 text-xs font-medium mt-4">Active Residents</p>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium text-slate-500">Society Meetings</p>
                        <span class="material-symbols-outlined text-lime-600 bg-lime-50 p-1.5 rounded-lg text-lg">event</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $counts['meetings']; ?></p>
                </div>
                <p class="text-primary text-xs font-semibold mt-4 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">calendar_month</span> Scheduled
                </p>
            </div>
            
            <div class="glass-card rounded-xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium text-slate-500">Maintenance Records</p>
                        <span class="material-symbols-outlined text-amber-500 bg-amber-50 p-1.5 rounded-lg text-lg">handyman</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white"><?php echo $counts['maintenance']; ?></p>
                </div>
                <p class="text-slate-500 text-xs font-medium mt-4">Total Entries</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Activities Table -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between px-2">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Recent Activities</h3>
                    <button class="text-primary text-sm font-semibold hover:underline flex items-center gap-1">
                        View All <span class="material-symbols-outlined text-sm">chevron_right</span>
                    </button>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 text-xs uppercase tracking-wider font-semibold">
                                <th class="px-6 py-4">Activity</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php if (empty($recentActivities)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="material-symbols-outlined text-3xl">inbox</span>
                                        <p>No recent activity found</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-<?php echo $activity['type'] === 'maintenance' ? 'amber' : 'blue'; ?>-100 dark:bg-<?php echo $activity['type'] === 'maintenance' ? 'amber' : 'blue'; ?>-900/30 flex items-center justify-center text-<?php echo $activity['type'] === 'maintenance' ? 'amber' : 'blue'; ?>-600">
                                                <span class="material-symbols-outlined text-lg">
                                                    <?php echo $activity['type'] === 'maintenance' ? 'handyman' : 'event'; ?>
                                                </span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo e($activity['title']); ?></p>
                                                <p class="text-xs text-slate-500"><?php echo ucfirst($activity['type']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        <?php echo date('M d, Y', strtotime($activity['date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-3 py-1 bg-<?php echo $activity['status'] === 'Success' ? 'green' : ($activity['status'] === 'Pending' ? 'amber' : 'blue'); ?>-100 text-<?php echo $activity['status'] === 'Success' ? 'green' : ($activity['status'] === 'Pending' ? 'amber' : 'blue'); ?>-700 dark:bg-<?php echo $activity['status'] === 'Success' ? 'green' : ($activity['status'] === 'Pending' ? 'amber' : 'blue'); ?>-900/30 dark:text-<?php echo $activity['status'] === 'Success' ? 'green' : ($activity['status'] === 'Pending' ? 'amber' : 'blue'); ?>-400 rounded-full text-xs font-bold">
                                            <?php echo e($activity['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-primary font-bold text-xs">View</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white px-2">Quick Actions</h3>
                <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                    <a href="society_fund.php" class="w-full flex items-center justify-between p-4 bg-primary text-white rounded-xl hover:bg-primary/90 transition-all group">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">account_balance_wallet</span>
                            <span class="font-semibold text-sm">View Society Fund</span>
                        </div>
                        <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
                    </a>
                    <a href="meetings.php" class="w-full flex items-center justify-between p-4 bg-background-light dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-all group border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">event</span>
                            <span class="font-semibold text-sm">View Meetings</span>
                        </div>
                        <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 transition-opacity text-primary">arrow_forward</span>
                    </a>
                    <a href="maintenance.php" class="w-full flex items-center justify-between p-4 bg-background-light dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-all group border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">handyman</span>
                            <span class="font-semibold text-sm">View Maintenance</span>
                        </div>
                        <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 transition-opacity text-primary">arrow_forward</span>
                    </a>
                    <a href="notes.php" class="w-full flex items-center justify-between p-4 bg-background-light dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-all group border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined">note_alt</span>
                            <span class="font-semibold text-sm">Manage Notes</span>
                        </div>
                        <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 transition-opacity text-primary">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>
</div>

<script>
// Interactive Sidebar Navigation
document.querySelectorAll('aside nav a').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!this.classList.contains('bg-white/10')) {
            e.preventDefault();
            // Remove active class from all links
            document.querySelectorAll('aside nav a').forEach(l => {
                l.classList.remove('bg-white/10', 'text-white');
                l.classList.add('text-white/70');
            });
            // Add active class to clicked link
            this.classList.add('bg-white/10', 'text-white');
            this.classList.remove('text-white/70');
        }
    });
});

// Search functionality
const searchInput = document.querySelector('input[type="text"]');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
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

// Activity table row click handlers
document.querySelectorAll('tbody tr').forEach(row => {
    row.addEventListener('click', function(e) {
        if (!e.target.closest('button') && !e.target.closest('a')) {
            this.style.backgroundColor = 'rgba(13, 150, 139, 0.1)';
            setTimeout(() => {
                this.style.backgroundColor = '';
            }, 300);
        }
    });
});
</script>

</body>
</html>
