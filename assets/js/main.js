/**
 * Interaksi ringan pada landing page Moma Bread.
 * Tanpa dependensi eksternal.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
     |  Tombol ganti tema (terang / gelap)
     |  Tema disimpan di localStorage dengan kunci "mb-theme".
     * ---------------------------------------------------------------- */
    var toggle = document.getElementById('themeToggle');

    if (toggle) {
        var applyIcon = function (theme) {
            toggle.innerHTML = theme === 'dark' ? '&#9789;' : '&#9788;';
            toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        };

        var current = function () {
            var saved = null;
            try {
                saved = localStorage.getItem('mb-theme');
            } catch (e) { /* diabaikan */ }

            if (saved === 'dark' || saved === 'light') {
                return saved;
            }

            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark'
                : 'light';
        };

        applyIcon(current());

        toggle.addEventListener('click', function () {
            var next = current() === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', next);
            applyIcon(next);

            try {
                localStorage.setItem('mb-theme', next);
            } catch (e) { /* diabaikan */ }
        });
    }

    /* ------------------------------------------------------------------
     |  Menu navigasi untuk layar kecil (drawer)
     * ---------------------------------------------------------------- */
    var navToggle  = document.getElementById('navToggle');
    var navClose   = document.getElementById('navClose');
    var navOverlay = document.getElementById('navOverlay');
    var navLinks   = document.getElementById('navlinks');

    function setNav(open) {
        document.body.classList.toggle('navopen', open);
        if (navToggle) {
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    if (navToggle) {
        navToggle.addEventListener('click', function () {
            setNav(!document.body.classList.contains('navopen'));
        });
    }
    if (navClose) {
        navClose.addEventListener('click', function () { setNav(false); });
    }
    if (navOverlay) {
        navOverlay.addEventListener('click', function () { setNav(false); });
    }

    // Tutup drawer setelah memilih tautan di dalamnya.
    if (navLinks) {
        navLinks.addEventListener('click', function (ev) {
            if (ev.target.closest('a')) {
                setNav(false);
            }
        });
    }

    // Tutup dengan tombol Escape, dan kembalikan fokus ke tombol buka.
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && document.body.classList.contains('navopen')) {
            setNav(false);
            if (navToggle) { navToggle.focus(); }
        }
    });

    /* ------------------------------------------------------------------
     |  Tandai link navigasi yang sedang aktif
     * ---------------------------------------------------------------- */
    var sections = ['menu', 'keunggulan', 'pesan', 'galeri', 'testimoni'];
    var links = document.querySelectorAll('nav .navlinks a.h');

    if (sections.length && links.length && 'IntersectionObserver' in window) {
        var spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }

                var anchor = '#' + entry.target.id;
                links.forEach(function (link) {
                    var href = link.getAttribute('href') || '';
                    link.classList.toggle('active', href === anchor || href.endsWith(anchor));
                });
            });
        }, { rootMargin: '-45% 0px -50% 0px' });

        sections.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                spy.observe(el);
            }
        });
    }

    /* ------------------------------------------------------------------
     |  Tombol "kembali ke atas"
     * ---------------------------------------------------------------- */
    var toTop = document.getElementById('toTop');

    if (toTop) {
        var onScroll = function () {
            toTop.classList.toggle('show', window.scrollY > 600);
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        toTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ------------------------------------------------------------------
     |  Gambar muncul halus setelah selesai dimuat
     * ---------------------------------------------------------------- */
    document.querySelectorAll('img[loading="lazy"]').forEach(function (image) {
        image.classList.add('lazy');

        var show = function () { image.classList.add('done'); };

        if (image.complete && image.naturalWidth > 0) {
            show();
        } else {
            image.addEventListener('load', show, { once: true });
            image.addEventListener('error', show, { once: true });
        }
    });

    /* ------------------------------------------------------------------
     |  Buka/tutup galeri saat diklik (tampilan besar)
     * ---------------------------------------------------------------- */
    document.querySelectorAll('.gal-item img').forEach(function (image) {
        image.addEventListener('click', function () {
            if (image.dataset.lightbox === '1') {
                return;
            }

            var box = document.createElement('div');
            box.className = 'lightbox';
            box.dataset.lightbox = '1';
            box.innerHTML = '<img src="' + image.src + '" alt="' + image.alt + '">';

            box.addEventListener('click', function () {
                box.remove();
                document.body.style.overflow = '';
            });

            document.body.appendChild(box);
            document.body.style.overflow = 'hidden';
        });
    });
})();
