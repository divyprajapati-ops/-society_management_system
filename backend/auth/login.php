<?php

require_once __DIR__ . '/../config/auth.php';

if (is_logged_in()) {
    redirect_by_role();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, email, password, role, society_id, building_id, status FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            // Log failed login attempt for security
            error_log("[SocietyApp] Failed login attempt for email: {$email}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $error = 'Invalid login.';
        } elseif (($user['status'] ?? 'inactive') !== 'active') {
            $error = 'Account is inactive.';
        } else {
            unset($user['password']);
            $_SESSION['user'] = $user;
            
            // Store IP and User Agent for optional session binding
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $_SESSION['last_regenerate'] = time();
            
            // Regenerate session ID on login to prevent fixation
            session_regenerate_id(true);
            
            redirect_by_role();
        }
    }
}

?>
<!doctype html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Login - Society App</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
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
        .glass {
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }
        .dark .glass {
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">
<div class="min-h-screen relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-primary/15 blur-3xl"></div>
        <div class="absolute top-1/3 -right-24 h-72 w-72 rounded-full bg-emerald-500/15 blur-3xl"></div>
        <div class="absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-indigo-500/10 blur-3xl"></div>
    </div>

    <div class="relative mx-auto max-w-6xl px-4 py-10 sm:py-14">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-11 w-11 rounded-xl bg-primary/15 text-primary flex items-center justify-center">
                    <span class="material-symbols-outlined">domain</span>
                </div>
                <div>
                    <div class="text-lg font-bold leading-tight">Society Management</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">Secure login for all roles</div>
                </div>
            </div>
            <button id="themeToggle" type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-900/60 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-900 transition">
                <span id="themeIcon" class="material-symbols-outlined text-[18px]">dark_mode</span>
                <span class="hidden sm:inline">Theme</span>
            </button>
        </div>

        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
            <div class="glass rounded-2xl p-7 sm:p-9 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">Society Management</h1>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Sign in to access your society dashboard.</p>
                    </div>
                    <div class="hidden sm:flex items-center gap-2 rounded-full bg-primary/10 text-primary px-3 py-1.5 text-xs font-semibold">
                        <span class="material-symbols-outlined text-sm">verified_user</span>
                        Secure
                    </div>
                </div>

                <div class="mt-6">
                    <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Select Your Role</div>
                    <div class="mt-3 grid grid-cols-2 gap-3" id="roleGrid">
                        <button type="button" data-role="society_admin" data-email="" class="role-btn group rounded-xl border border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-900/50 px-4 py-4 text-left hover:border-primary/40 hover:bg-white dark:hover:bg-slate-900 transition">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
                                <div class="text-sm font-semibold">Society Admin</div>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Manage entire society</div>
                        </button>
                        <button type="button" data-role="pramukh" data-email="" class="role-btn group rounded-xl border border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-900/50 px-4 py-4 text-left hover:border-primary/40 hover:bg-white dark:hover:bg-slate-900 transition">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">support_agent</span>
                                <div class="text-sm font-semibold">Pramukh</div>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Building head</div>
                        </button>
                        <button type="button" data-role="building_admin" data-email="" class="role-btn group rounded-xl border border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-900/50 px-4 py-4 text-left hover:border-primary/40 hover:bg-white dark:hover:bg-slate-900 transition">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">apartment</span>
                                <div class="text-sm font-semibold">Building Admin</div>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Building management</div>
                        </button>
                        <button type="button" data-role="member" data-email="" class="role-btn group rounded-xl border border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-900/50 px-4 py-4 text-left hover:border-primary/40 hover:bg-white dark:hover:bg-slate-900 transition">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">person</span>
                                <div class="text-sm font-semibold">Member</div>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Resident/Owner</div>
                        </button>
                    </div>
                </div>

                <div class="mt-6 rounded-2xl bg-white/60 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 p-5">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">shield</span>
                        <div class="text-sm font-semibold">Secure Platform</div>
                    </div>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        This platform uses encrypted authentication and role-based access control to ensure your society data remains protected.
                    </p>
                </div>
            </div>

            <div class="glass rounded-2xl p-7 sm:p-9 shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold text-slate-500 dark:text-slate-400">Sign in</div>
                        <h2 class="text-2xl font-bold">Account Login</h2>
                    </div>
                    <div class="h-12 w-12 rounded-2xl bg-primary/15 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined">login</span>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="mt-6 rounded-xl border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-base">error</span>
                            <div><?php echo e($error); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="mt-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-2">Email</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">mail</span>
                            <input id="email" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-950/40 pl-11 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20" type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200 mb-2">Password</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                            <input id="password" class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-950/40 pl-11 pr-11 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20" type="password" name="password" placeholder="Enter your password" required>
                            <button id="togglePassword" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <span id="eyeIcon" class="material-symbols-outlined text-[20px]">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30 transition" type="submit">
                        <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                        Sign in
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-8 text-center text-xs text-slate-500 dark:text-slate-400">
            © <?php echo date('Y'); ?> Society App. All rights reserved.
        </div>
    </div>
</div>

<script>
    (function () {
        const root = document.documentElement;
        const saved = localStorage.getItem('theme');
        if (saved === 'dark') {
            root.classList.add('dark');
        }

        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const setIcon = () => {
            const isDark = root.classList.contains('dark');
            themeIcon.textContent = isDark ? 'light_mode' : 'dark_mode';
        };
        setIcon();
        themeToggle.addEventListener('click', () => {
            root.classList.toggle('dark');
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
            setIcon();
        });

        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        togglePassword.addEventListener('click', () => {
            const isHidden = password.getAttribute('type') === 'password';
            password.setAttribute('type', isHidden ? 'text' : 'password');
            eyeIcon.textContent = isHidden ? 'visibility_off' : 'visibility';
        });

        // Role selection
        const roleButtons = Array.from(document.querySelectorAll('.role-btn'));
        roleButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                roleButtons.forEach(b => b.classList.remove('ring-2', 'ring-primary/30', 'border-primary/40'));
                btn.classList.add('ring-2', 'ring-primary/30', 'border-primary/40');
            });
        });
    })();
</script>
</body>
</html>
