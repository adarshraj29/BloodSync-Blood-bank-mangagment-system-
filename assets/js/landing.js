/* =========================================================
   BloodSync Landing Page JS
   Animations, Theme Toggle, Counters, Progress Rings
========================================================= */

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. Initialize AOS Animations ---
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });
    }

    // --- 2. Navbar Scroll Effect ---
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // --- 3. Light/Dark Mode Toggle ---
    const themeBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    
    // Check saved theme
    if (localStorage.getItem('bloodsync_theme') === 'light') {
        document.body.classList.add('light-mode');
        if (themeIcon) {
            themeIcon.classList.remove('bi-sun');
            themeIcon.classList.add('bi-moon-stars');
        }
    }

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            document.body.classList.toggle('light-mode');
            
            if (document.body.classList.contains('light-mode')) {
                localStorage.setItem('bloodsync_theme', 'light');
                themeIcon.classList.remove('bi-sun');
                themeIcon.classList.add('bi-moon-stars');
            } else {
                localStorage.setItem('bloodsync_theme', 'dark');
                themeIcon.classList.remove('bi-moon-stars');
                themeIcon.classList.add('bi-sun');
            }
        });
    }

    // --- 4. Number Counter Animation ---
    const counters = document.querySelectorAll('.stat-number');
    let counted = false;

    const animateCounters = () => {
        counters.forEach(counter => {
            const target = +counter.getAttribute('data-target');
            const duration = 2000; // ms
            const increment = target / (duration / 16); // 60fps

            let current = 0;
            const updateCounter = () => {
                current += increment;
                if (current < target) {
                    counter.innerText = Math.ceil(current) + '+';
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.innerText = target + '+';
                }
            };
            updateCounter();
        });
    };

    // Use Intersection Observer to trigger counter when visible
    const statsSection = document.querySelector('.stats-container');
    if (statsSection) {
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !counted) {
                animateCounters();
                counted = true;
            }
        }, { threshold: 0.5 });
        observer.observe(statsSection);
    }

    // --- 5. Circular Progress Bar Animation (Blood Stock) ---
    const progressRings = document.querySelectorAll('.progress-ring');
    const animateRings = () => {
        progressRings.forEach(ring => {
            const percent = ring.getAttribute('data-percent');
            // Animate by updating CSS variable slowly
            let current = 0;
            const updateRing = () => {
                current += 2;
                if (current <= percent) {
                    ring.style.setProperty('--percent', current + '%');
                    requestAnimationFrame(updateRing);
                } else {
                    ring.style.setProperty('--percent', percent + '%');
                }
            };
            updateRing();
        });
    };

    // Use Intersection Observer for rings
    const stockSection = document.getElementById('live-stock');
    if (stockSection) {
        let ringsAnimated = false;
        const ringObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !ringsAnimated) {
                animateRings();
                ringsAnimated = true;
            }
        }, { threshold: 0.2 });
        ringObserver.observe(stockSection);
    }

    // --- 6. Demo Toast Notification ---
    const showToastBtn = document.getElementById('demoToastBtn');
    const toast = document.getElementById('demoToast');
    
    if (toast) {
        // Automatically show a demo toast after 5 seconds to showcase features
        setTimeout(() => {
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000); // Hide after 4s
        }, 5000);
    }
});
