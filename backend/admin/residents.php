<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['society_admin']);

$societyId = require_society_scope();

$success = null;
$error = null;

$stmt = $pdo->prepare('SELECT id, building_name FROM buildings WHERE society_id = ? ORDER BY building_name ASC');
$stmt->execute([$societyId]);
$buildings = $stmt->fetchAll();

// Get residents: only members (role='member') who have logged in at least once (last_login IS NOT NULL)
// Exclude building admins who just add data but don't actually live there
$stmt = $pdo->prepare('SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, u.last_login, b.building_name
    FROM users u
    LEFT JOIN buildings b ON b.id = u.building_id
    WHERE u.society_id = ? 
    AND u.role = "member" 
    AND u.last_login IS NOT NULL
    ORDER BY u.last_login DESC');
$stmt->execute([$societyId]);
$residents = $stmt->fetchAll();

$user = current_user();

$stmt = $pdo->prepare('SELECT name FROM society WHERE id = ?');
$stmt->execute([$societyId]);
$society = $stmt->fetch();

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Residents - <?php echo e($society['name'] ?? 'Society'); ?></title>
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
                    <span class="material-symbols-outlined text-white">domain</span>
                </div>
                <div class="flex flex-col">
                    <h1 class="text-white text-lg font-bold leading-tight">Society Admin</h1>
                    <p class="text-white/70 text-xs font-normal"><?php echo e($society['name'] ?? ''); ?></p>
                </div>
            </div>
            <nav class="flex flex-col gap-1">
                <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
                <a href="buildings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">apartment</span><span>Buildings</span></a>
                <a href="users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">manage_accounts</span><span>Users & Roles</span></a>
                <a href="residents.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium"><span class="material-symbols-outlined">groups</span><span>Residents</span></a>
                <a href="meetings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">event</span><span>Meetings</span></a>
                <a href="society_fund.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">account_balance</span><span>Society Fund</span></a>
                <a href="maintenance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">engineering</span><span>Maintenance</span></a>
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
                    <h2 class="text-[#0f172a] dark:text-white text-xl font-bold">Society Residents</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Active members who have logged in</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="p-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-400 relative"><span class="material-symbols-outlined">notifications</span><span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-gray-900"></span></button>
                <div class="h-8 w-[1px] bg-gray-200 dark:bg-gray-700 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-[#0f172a] dark:text-white"><?php echo e($user['name'] ?? 'Admin'); ?></p>
                        <p class="text-xs text-gray-500">Society Admin</p>
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Total Residents</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo count($residents); ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400"><span class="material-symbols-outlined">groups</span></div>
                    </div>
                </div>
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Active</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo count(array_filter($residents, fn($r) => $r['status'] === 'active')); ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400"><span class="material-symbols-outlined">check_circle</span></div>
                    </div>
                </div>
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Buildings</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo count($buildings); ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400"><span class="material-symbols-outlined">apartment</span></div>
                    </div>
                </div>
            </div>

            <!-- Info Alert -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-400 px-4 py-3 rounded-lg">
                <div class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-lg">info</span>
                    <div>
                        <p class="font-medium">Residents List</p>
                        <p class="text-sm mt-1">This list shows only actual society members (residents) who have logged in at least once. Building admins and data entry users are not shown here.</p>
                    </div>
                </div>
            </div>

            <!-- Residents Table -->
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div><h3 class="text-lg font-bold text-[#0f172a] dark:text-white">Resident Details</h3><p class="text-sm text-gray-500 mt-1">Members with login history</p></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Building</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Last Login</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <?php foreach ($residents as $r): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo e((string)$r['id']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white font-medium"><?php echo e($r['name']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo e($r['email']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white"><?php echo e((string)($r['building_name'] ?? '-')); ?></td>
                                    <td class="px-6 py-4"><span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $r['status'] === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'; ?>"><?php echo e($r['status']); ?></span></td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo e(date('M d, Y', strtotime($r['created_at']))); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><?php echo e(date('M d, Y h:i A', strtotime($r['last_login']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$residents): ?>
                                <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400"><span class="material-symbols-outlined text-4xl mb-2 block text-gray-300 dark:text-gray-600">groups</span><p>No residents found</p><p class="text-sm mt-1">Members who log in will appear here</p></td></tr>
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
