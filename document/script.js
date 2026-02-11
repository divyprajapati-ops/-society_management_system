// Society Management App - Documentation JavaScript

(function() {
    'use strict';

    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = themeToggle?.querySelector('.material-symbols-outlined');
    const html = document.documentElement;

    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
        if (themeIcon) themeIcon.textContent = 'light_mode';
    }

    themeToggle?.addEventListener('click', () => {
        html.classList.toggle('dark');
        const isDark = html.classList.contains('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        if (themeIcon) themeIcon.textContent = isDark ? 'light_mode' : 'dark_mode';
    });

    // Mobile Menu
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    let mobileMenu = document.getElementById('mobileMenu');

    if (!mobileMenu) {
        mobileMenu = document.createElement('div');
        mobileMenu.id = 'mobileMenu';
        mobileMenu.className = 'fixed top-0 right-0 h-full w-64 bg-white dark:bg-slate-900 shadow-2xl z-50 p-6 transform translate-x-full transition-transform duration-300 md:hidden';
        mobileMenu.innerHTML = `
            <div class="flex justify-end mb-8">
                <button id="closeMobileMenu" class="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 transition">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <nav class="flex flex-col gap-4">
                <a href="#overview" class="mobile-nav-link text-slate-600 dark:text-slate-300 font-medium py-2">Overview</a>
                <a href="#architecture" class="mobile-nav-link text-slate-600 dark:text-slate-300 font-medium py-2">Architecture</a>
                <a href="#roles" class="mobile-nav-link text-slate-600 dark:text-slate-300 font-medium py-2">Roles</a>
                <a href="#database" class="mobile-nav-link text-slate-600 dark:text-slate-300 font-medium py-2">Database</a>
                <a href="#api" class="mobile-nav-link text-slate-600 dark:text-slate-300 font-medium py-2">API</a>
                <a href="#quick-start" class="mobile-nav-link text-slate-600 dark:text-slate-300 font-medium py-2">Quick Start</a>
            </nav>
        `;
        document.body.appendChild(mobileMenu);

        document.getElementById('closeMobileMenu')?.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
        });

        mobileMenu.querySelectorAll('.mobile-nav-link').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
            });
        });
    }

    mobileMenuBtn?.addEventListener('click', () => {
        mobileMenu?.classList.toggle('active');
    });

    // Back to Top Button
    const backToTop = document.getElementById('backToTop');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 500) {
            backToTop?.classList.remove('opacity-0', 'pointer-events-none');
            backToTop?.classList.add('opacity-100', 'pointer-events-auto');
        } else {
            backToTop?.classList.add('opacity-0', 'pointer-events-none');
            backToTop?.classList.remove('opacity-100', 'pointer-events-auto');
        }
    });

    backToTop?.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Scroll Reveal Animation - Disabled for faster loading
    // All content is now visible immediately on page load

    // Smooth Scroll for Navigation Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const offsetTop = target.offsetTop - 80;
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });

    // Active Navigation Highlight - Throttled for performance
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link');
    let ticking = false;

    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                let current = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    if (window.scrollY >= sectionTop - 200) {
                        current = section.getAttribute('id');
                    }
                });

                navLinks.forEach(link => {
                    link.classList.remove('text-primary');
                    if (link.getAttribute('href') === `#${current}`) {
                        link.classList.add('text-primary');
                    }
                });
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // Copy Code Functionality
    document.querySelectorAll('pre code').forEach(block => {
        const button = document.createElement('button');
        button.className = 'absolute top-2 right-2 p-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-xs opacity-0 transition-opacity duration-200';
        button.innerHTML = '<span class="material-symbols-outlined text-sm">content_copy</span>';
        
        const pre = block.parentElement;
        pre.style.position = 'relative';
        pre.appendChild(button);

        pre.addEventListener('mouseenter', () => button.classList.remove('opacity-0'));
        pre.addEventListener('mouseleave', () => button.classList.add('opacity-0'));

        button.addEventListener('click', () => {
            navigator.clipboard.writeText(block.textContent).then(() => {
                button.innerHTML = '<span class="material-symbols-outlined text-sm">check</span>';
                setTimeout(() => {
                    button.innerHTML = '<span class="material-symbols-outlined text-sm">content_copy</span>';
                }, 2000);
            });
        });
    });

    // Stats Counter Animation
    const animateCounter = (el, target) => {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                el.textContent = target;
                clearInterval(timer);
            } else {
                el.textContent = Math.floor(current);
            }
        }, 30);
    };

    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.textContent);
                animateCounter(el, target);
                statsObserver.unobserve(el);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.glass-card .text-3xl').forEach(stat => {
        statsObserver.observe(stat);
    });

    // Typing Effect for Hero Subtitle (optional enhancement)
    const typewriterElements = document.querySelectorAll('.typewriter');
    typewriterElements.forEach(el => {
        const text = el.textContent;
        el.textContent = '';
        let i = 0;
        const typeInterval = setInterval(() => {
            if (i < text.length) {
                el.textContent += text.charAt(i);
                i++;
            } else {
                clearInterval(typeInterval);
            }
        }, 50);
    });

    // Parallax Effect - Disabled for better performance
    // Background animations removed for smooth scrolling

    // Role Card Click Effect
    document.querySelectorAll('.role-card').forEach(card => {
        card.addEventListener('click', () => {
            card.style.transform = 'scale(0.98)';
            setTimeout(() => {
                card.style.transform = '';
            }, 150);
        });
    });

    // Loading Animation
    window.addEventListener('load', () => {
        document.body.classList.add('loaded');
    });

    // Keyboard Navigation
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileMenu?.classList.contains('active')) {
            mobileMenu.classList.remove('active');
        }
    });

    // Console Easter Egg
    console.log('%c🏢 Society Management App', 'font-size: 24px; font-weight: bold; color: #1e3b8a;');
    console.log('%cDocumentation v1.0', 'font-size: 14px; color: #10b981;');
    console.log('%cBuilt with ❤️ using PHP, MySQL, Tailwind CSS', 'font-size: 12px; color: #64748b;');

})();
