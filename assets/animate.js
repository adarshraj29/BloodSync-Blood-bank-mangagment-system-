// BloodSync — shared animation helpers
document.addEventListener('DOMContentLoaded', function () {

    // ── Fade-in cards on scroll ──────────────────────────────
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity    = '1';
                entry.target.style.transform  = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    document.querySelectorAll('.stat-card, .card').forEach((el, i) => {
        el.style.opacity   = '0';
        el.style.transform = 'translateY(18px)';
        el.style.transition = `opacity 0.5s ease ${i * 0.06}s, transform 0.5s ease ${i * 0.06}s`;
        observer.observe(el);
    });

    // ── Button ripple effect ─────────────────────────────────
    document.querySelectorAll('.btn-danger').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const ripple = document.createElement('span');
            const rect   = btn.getBoundingClientRect();
            const size   = Math.max(rect.width, rect.height);
            ripple.style.cssText = `
                position:absolute;border-radius:50%;
                width:${size}px;height:${size}px;
                left:${e.clientX - rect.left - size/2}px;
                top:${e.clientY - rect.top  - size/2}px;
                background:rgba(255,255,255,0.3);
                transform:scale(0);animation:rippleAnim 0.5s ease;
                pointer-events:none;
            `;
            btn.style.position   = 'relative';
            btn.style.overflow   = 'hidden';
            btn.appendChild(ripple);
            ripple.addEventListener('animationend', () => ripple.remove());
        });
    });

    // ── Stat counter animation ───────────────────────────────
    document.querySelectorAll('.stat-value').forEach(el => {
        const target = parseInt(el.textContent.replace(/\D/g,''));
        if (isNaN(target) || target === 0) return;
        let current  = 0;
        const step   = Math.max(1, Math.ceil(target / 40));
        const timer  = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current;
            if (current >= target) clearInterval(timer);
        }, 30);
    });

    // ── Sidebar active link highlight ───────────────────────
    const path = window.location.pathname;
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
        if (link.getAttribute('href') && path.endsWith(link.getAttribute('href').split('/').pop())) {
            link.classList.add('active');
        }
    });
});

// Add ripple keyframe once
const styleEl = document.createElement('style');
styleEl.textContent = `
    @keyframes rippleAnim {
        to { transform:scale(2.5); opacity:0; }
    }
`;
document.head.appendChild(styleEl);
