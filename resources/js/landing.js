// Interações leves da landing page: sombra do header ao rolar, menu mobile e
// reveal das seções ao entrar na viewport.

function onReady(fn) {
    if (document.readyState !== 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

onReady(function () {
    // Sombra do header ao rolar
    const header = document.querySelector('[data-landing-header]');
    if (header) {
        const toggleShadow = () => {
            header.classList.toggle('shadow-sm', window.scrollY > 8);
            header.classList.toggle('bg-white/90', window.scrollY > 8);
        };
        toggleShadow();
        window.addEventListener('scroll', toggleShadow, { passive: true });
    }

    // Menu mobile
    const navToggle = document.querySelector('[data-nav-toggle]');
    const mobileNav = document.querySelector('[data-mobile-nav]');
    if (navToggle && mobileNav) {
        navToggle.addEventListener('click', () => mobileNav.classList.toggle('hidden'));
        mobileNav.querySelectorAll('a').forEach((a) =>
            a.addEventListener('click', () => mobileNav.classList.add('hidden'))
        );
    }

    // Reveal ao rolar
    const reveals = document.querySelectorAll('.mn-reveal');
    if (reveals.length && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        io.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: '0px 0px -10% 0px', threshold: 0.08 }
        );
        reveals.forEach((el) => io.observe(el));
    } else {
        reveals.forEach((el) => el.classList.add('is-visible'));
    }
});
