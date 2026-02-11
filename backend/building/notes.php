<?php

require_once __DIR__ . '/../config/auth.php';
require_role(['building_admin']);

$buildingId = require_building_scope();
$user = current_user();

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim((string)($_POST['message'] ?? ''));

    if ($message === '') {
        $error = 'Message is required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, level, message) VALUES (?, 'building', ?)");
        $stmt->execute([(int)$user['id'], $message]);
        $success = 'Note added.';
        $_POST = [];
    }
}

$stmt = $pdo->prepare("SELECT n.message, n.created_at, u.name AS user_name
    FROM notes n
    JOIN users u ON u.id = n.user_id
    WHERE n.level='building' AND u.building_id = ?
    ORDER BY n.created_at DESC, n.id DESC");
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
    <title>Building Notes - <?php echo e($building['building_name'] ?? 'Building'); ?></title>
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
                <a href="members.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">person</span><span>Members</span></a>
                <a href="maintenance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">engineering</span><span>Maintenance</span></a>
                <a href="reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-white/80 transition-colors"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
                <a href="notes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/10 text-white font-medium"><span class="material-symbols-outlined">sticky_note_2</span><span>Notes</span></a>
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
                    <h2 class="text-[#0f172a] dark:text-white text-xl font-bold">Building Notes</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Manage building communications</p>
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

            <!-- KPI Card -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="glass-card rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <div><p class="text-xs font-semibold text-gray-500 uppercase">Total Notes</p><p class="mt-1 text-2xl font-bold text-[#0f172a] dark:text-white"><?php echo count($rows); ?></p></div>
                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400"><span class="material-symbols-outlined">sticky_note_2</span></div>
                    </div>
                </div>
            </div>

            <!-- Add Note Form -->
            <div class="glass-card rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-800">
                <h3 class="text-lg font-bold text-[#0f172a] dark:text-white mb-4 flex items-center gap-2"><span class="material-symbols-outlined">edit_note</span>Add New Note</h3>
                <form method="post" action="" class="space-y-4">
                    <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Message</label><textarea name="message" required rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-transparent dark:bg-gray-800 dark:text-white resize-none" placeholder="Write your note here..."><?php echo e($_POST['message'] ?? ''); ?></textarea></div>
                    <div class="flex justify-end"><button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors font-medium"><span class="material-symbols-outlined text-[18px]">add</span>Add Note</button></div>
                </form>
            </div>

            <!-- Notes List -->
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-gray-800"><h3 class="text-lg font-bold text-[#0f172a] dark:text-white">Building Notes</h3><p class="text-sm text-gray-500 mt-1">Recent communications</p></div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <?php foreach ($rows as $r): ?>
                        <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0"><span class="material-symbols-outlined text-primary">person</span></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1"><span class="font-semibold text-gray-900 dark:text-white"><?php echo e($r['user_name']); ?></span><span class="text-xs text-gray-500"><?php echo e(date('M d, Y \a\t h:i A', strtotime($r['created_at']))); ?></span></div>
                                    <div class="text-gray-700 dark:text-gray-300 text-sm whitespace-pre-wrap"><?php echo nl2br(e($r['message'])); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                        <div class="p-10 text-center text-gray-500 dark:text-gray-400"><span class="material-symbols-outlined text-4xl mb-2 block text-gray-300 dark:text-gray-600">sticky_note_2</span><p>No notes yet</p><p class="text-sm mt-1">Add a note to get started</p></div>
                    <?php endif; ?>
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
