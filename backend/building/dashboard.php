<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['building_admin']);

$buildingId = require_building_scope();

$stmt = $pdo->prepare('SELECT b.id, b.building_name, s.name AS society_name FROM buildings b JOIN society s ON s.id = b.society_id WHERE b.id = ?');
$stmt->execute([$buildingId]);
$building = $stmt->fetch();

$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0) AS income_total,
    COALESCE(SUM(CASE WHEN type IN ('expense','use_money') THEN amount ELSE 0 END),0) AS expense_total
    FROM building_fund WHERE building_id = ?");
$stmt->execute([$buildingId]);
$fund = $stmt->fetch();
$fundTotal = (float)($fund['income_total'] ?? 0) - (float)($fund['expense_total'] ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE building_id = ? AND role='member'");
$stmt->execute([$buildingId]);
$memberCount = (int)($stmt->fetch()['c'] ?? 0);

$user = current_user();
$currentMonth = date('Y-m');

$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM meetings WHERE level='building' AND building_id = ?");
$stmt->execute([$buildingId]);
$meetingCount = (int)($stmt->fetch()['c'] ?? 0);

$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN m.status='paid' THEN 1 ELSE 0 END),0) AS paid_count,
    COALESCE(SUM(CASE WHEN m.status='unpaid' THEN 1 ELSE 0 END),0) AS unpaid_count,
    COALESCE(SUM(CASE WHEN m.status='paid' THEN m.amount ELSE 0 END),0) AS paid_amount,
    COALESCE(SUM(CASE WHEN m.status='unpaid' THEN m.amount ELSE 0 END),0) AS unpaid_amount
    FROM maintenance m
    JOIN users u ON u.id = m.member_id
    WHERE u.building_id = ? AND m.month = ?");
$stmt->execute([$buildingId, $currentMonth]);
$maintThisMonth = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT title, meeting_date, created_at
    FROM meetings
    WHERE level='building' AND building_id = ? AND meeting_date >= CURDATE()
    ORDER BY meeting_date ASC, id ASC
    LIMIT 5");
$stmt->execute([$buildingId]);
$upcomingMeetings = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT amount, type, date, created_at
    FROM building_fund
    WHERE building_id = ?
    ORDER BY date DESC, id DESC
    LIMIT 6");
$stmt->execute([$buildingId]);
$recentFund = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT m.amount, m.month, m.status, m.created_at, u.name AS member_name
    FROM maintenance m
    JOIN users u ON u.id = m.member_id
    WHERE u.building_id = ?
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT 6");
$stmt->execute([$buildingId]);
$recentMaintenance = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT title, meeting_date, created_at
    FROM meetings
    WHERE level='building' AND building_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 6");
$stmt->execute([$buildingId]);
$recentMeetings = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT
    DATE_FORMAT(date, '%b') AS label,
    YEAR(date) AS y,
    MONTH(date) AS m,
    SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS income,
    SUM(CASE WHEN type IN ('expense','use_money') THEN amount ELSE 0 END) AS expense
    FROM building_fund
    WHERE building_id = ?
    AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(date), MONTH(date), DATE_FORMAT(date, '%b')
    ORDER BY YEAR(date), MONTH(date)");
$stmt->execute([$buildingId]);
$monthlyData = $stmt->fetchAll();

$monthLabels = [];
$monthIncome = [];
$monthExpense = [];
foreach ($monthlyData as $md) {
    $monthLabels[] = (string)($md['label'] ?? '');
    $monthIncome[] = (float)($md['income'] ?? 0);
    $monthExpense[] = (float)($md['expense'] ?? 0);
}

$activities = [];
foreach ($recentFund as $r) {
    $t = (string)($r['type'] ?? '');
    $title = ($t === 'income') ? 'Fund Income' : (($t === 'expense') ? 'Fund Expense' : 'Fund Used');
    $activities[] = [
        'ts' => (string)($r['created_at'] ?? ''),
        'kind' => 'fund',
        'title' => $title,
        'meta' => (string)($r['date'] ?? ''),
        'amount' => (float)($r['amount'] ?? 0),
        'badge' => $t,
    ];
}
foreach ($recentMaintenance as $r) {
    $status = (string)($r['status'] ?? 'unpaid');
    $month = (string)($r['month'] ?? '');
    $label = $month ? date('M Y', strtotime($month . '-01')) : '';
    $activities[] = [
        'ts' => (string)($r['created_at'] ?? ''),
        'kind' => 'maintenance',
        'title' => ($status === 'paid') ? 'Maintenance Paid' : 'Maintenance Unpaid',
        'meta' => trim((string)($r['member_name'] ?? '') . ' • ' . $label),
        'amount' => (float)($r['amount'] ?? 0),
        'badge' => $status,
    ];
}
foreach ($recentMeetings as $r) {
    $activities[] = [
        'ts' => (string)($r['created_at'] ?? ''),
        'kind' => 'meeting',
        'title' => (string)($r['title'] ?? 'Meeting'),
        'meta' => (string)($r['meeting_date'] ?? ''),
        'amount' => null,
        'badge' => 'meeting',
    ];
}
usort($activities, static function ($a, $b) {
    return strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? ''));
});
$activities = array_slice($activities, 0, 8);

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Building Dashboard - <?php echo e($building['building_name'] ?? 'Building'); ?></title>
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
                        primary: "#4f46e5",
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
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="fund.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors">
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
                    <h2 class="text-[#0f172a] dark:text-white text-xl font-bold">Dashboard</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($building['society_name'] ?? ''); ?></p>
                </div>
                <div class="max-w-md w-full relative hidden md:block">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input class="w-full bg-gray-100 dark:bg-gray-800 border-none rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-primary/20 text-sm" placeholder="Search members, meetings..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="window.location.href='meetings.php'" class="hidden sm:flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors text-sm font-medium">
                    <span class="material-symbols-outlined text-lg">add_circle</span>
                    <span>New Meeting</span>
                </button>
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
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="flex items-start justify-between gap-6 flex-col sm:flex-row">
                    <div>
                        <h3 class="text-xl font-bold text-[#0f172a] dark:text-white"><?php echo e($building['building_name'] ?? ''); ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Access restricted to your building only</p>
                        <div class="mt-4 flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
                            <span class="material-symbols-outlined text-primary">domain</span>
                            <span><?php echo e($building['society_name'] ?? ''); ?></span>
                        </div>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-sm text-gray-500">Building Fund Balance</p>
                        <p class="text-3xl font-bold text-primary">₹<?php echo number_format((float)$fundTotal, 2); ?></p>
                        <p class="mt-1 text-xs text-gray-500">Income: ₹<?php echo number_format((float)($fund['income_total'] ?? 0), 2); ?> • Expense: ₹<?php echo number_format((float)($fund['expense_total'] ?? 0), 2); ?></p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Members</p>
                            <p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo e((string)$memberCount); ?></p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <span class="material-symbols-outlined">group</span>
                        </div>
                    </div>
                    <a href="members.php" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                        View members
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </a>
                </div>

                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Meetings</p>
                            <p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo e((string)$meetingCount); ?></p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <span class="material-symbols-outlined">event</span>
                        </div>
                    </div>
                    <a href="meetings.php" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                        Manage meetings
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </a>
                </div>

                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Maintenance Paid</p>
                            <p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo e((string)($maintThisMonth['paid_count'] ?? 0)); ?></p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center text-violet-600 dark:text-violet-400">
                            <span class="material-symbols-outlined">check_circle</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">This month: ₹<?php echo number_format((float)($maintThisMonth['paid_amount'] ?? 0), 2); ?></p>
                </div>

                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Maintenance Unpaid</p>
                            <p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo e((string)($maintThisMonth['unpaid_count'] ?? 0)); ?></p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-700 dark:text-amber-400">
                            <span class="material-symbols-outlined">schedule</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">This month: ₹<?php echo number_format((float)($maintThisMonth['unpaid_amount'] ?? 0), 2); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-[#0f172a] dark:text-white">Fund Trend</h3>
                                <p class="text-sm text-gray-500 mt-1">Last 6 months income vs expense</p>
                            </div>
                            <a href="fund.php" class="text-sm font-medium text-primary hover:underline">View fund</a>
                        </div>
                    </div>
                    <div class="p-6">
                        <canvas id="fundChart" height="110"></canvas>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-lg font-bold text-[#0f172a] dark:text-white">Upcoming Meetings</h3>
                        <p class="text-sm text-gray-500 mt-1">Next 5 scheduled</p>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php foreach ($upcomingMeetings as $m): ?>
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400 flex-shrink-0">
                                        <span class="material-symbols-outlined text-[20px]">event</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e((string)$m['title']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo e(date('M d, Y', strtotime((string)$m['meeting_date']))); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$upcomingMeetings): ?>
                                <div class="text-sm text-gray-500 dark:text-gray-400">No upcoming meetings.</div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-6">
                            <a href="meetings.php" class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline">
                                Manage meetings
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-bold text-[#0f172a] dark:text-white">Recent Activity</h3>
                    <p class="text-sm text-gray-500 mt-1">Latest updates from your building</p>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($activities as $a): ?>
                            <?php
                            $kind = (string)($a['kind'] ?? '');
                            $badge = (string)($a['badge'] ?? '');
                            $icon = 'bolt';
                            $bg = 'bg-slate-100 dark:bg-slate-800/50';
                            $fg = 'text-slate-700 dark:text-slate-200';
                            if ($kind === 'fund') {
                                $icon = 'account_balance_wallet';
                                if ($badge === 'income') { $bg = 'bg-emerald-100 dark:bg-emerald-900/30'; $fg = 'text-emerald-700 dark:text-emerald-300'; }
                                if ($badge === 'expense') { $bg = 'bg-amber-100 dark:bg-amber-900/30'; $fg = 'text-amber-800 dark:text-amber-300'; }
                                if ($badge === 'use_money') { $bg = 'bg-blue-100 dark:bg-blue-900/30'; $fg = 'text-blue-700 dark:text-blue-300'; }
                            } elseif ($kind === 'maintenance') {
                                $icon = 'engineering';
                                if ($badge === 'paid') { $bg = 'bg-violet-100 dark:bg-violet-900/30'; $fg = 'text-violet-700 dark:text-violet-300'; }
                                if ($badge === 'unpaid') { $bg = 'bg-rose-100 dark:bg-rose-900/30'; $fg = 'text-rose-700 dark:text-rose-300'; }
                            } elseif ($kind === 'meeting') {
                                $icon = 'event';
                                $bg = 'bg-emerald-100 dark:bg-emerald-900/30';
                                $fg = 'text-emerald-700 dark:text-emerald-300';
                            }
                            ?>
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-full <?php echo e($bg); ?> flex items-center justify-center <?php echo e($fg); ?> flex-shrink-0">
                                    <span class="material-symbols-outlined text-[20px]"><?php echo e($icon); ?></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-4">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e((string)($a['title'] ?? '')); ?></p>
                                        <p class="text-xs text-gray-500 whitespace-nowrap"><?php echo e($a['ts'] ? date('M d, H:i', strtotime((string)$a['ts'])) : ''); ?></p>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate"><?php echo e((string)($a['meta'] ?? '')); ?></p>
                                </div>
                                <div class="text-right">
                                    <?php if ($a['amount'] !== null): ?>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">₹<?php echo number_format((float)$a['amount'], 2); ?></p>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400">—</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$activities): ?>
                            <div class="text-sm text-gray-500 dark:text-gray-400">No recent activity.</div>
                        <?php endif; ?>
                    </div>
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

const chartEl = document.getElementById('fundChart');
if (chartEl) {
    const labels = <?php echo json_encode($monthLabels, JSON_UNESCAPED_UNICODE); ?>;
    const income = <?php echo json_encode($monthIncome, JSON_UNESCAPED_UNICODE); ?>;
    const expense = <?php echo json_encode($monthExpense, JSON_UNESCAPED_UNICODE); ?>;

    new Chart(chartEl, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Income',
                    data: income,
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.12)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 3,
                },
                {
                    label: 'Expense',
                    data: expense,
                    borderColor: 'rgba(245, 158, 11, 1)',
                    backgroundColor: 'rgba(245, 158, 11, 0.10)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 3,
                },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 10,
                        usePointStyle: true,
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ₹${Number(ctx.raw || 0).toFixed(2)}`
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: (v) => '₹' + v,
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.18)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}
</script>

</body>
</html>
