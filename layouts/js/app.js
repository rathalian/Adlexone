/* Adlexone layout behaviour */

(() => {
    const wrap = document.querySelector('.wrap');
    const sidebar = document.querySelector('.sidebar');
    if (wrap && sidebar) wrap.classList.add('has-sidebar');
})();

/* Current left-nav item: ?option=, then path */
(() => {
    const norm = (value) => (value || '').toString().trim().toLowerCase();
    const links = Array.from(document.querySelectorAll('.sidebar .navbtn[href]'));
    if (!links.length) return;

    links.forEach((link) => {
        link.classList.remove('active');
        link.removeAttribute('aria-current');
    });

    const url = new URL(location.href);
    const currentOpt = norm(url.searchParams.get('option'));
    const currentPath = url.pathname.replace(/\/+$/, '');

    const matchesOption = (link) => {
        if (norm(link.getAttribute('data-option')) === currentOpt) return true;
        try {
            return norm(new URL(link.getAttribute('href'), location.href).searchParams.get('option')) === currentOpt;
        } catch (e) {
            return false;
        }
    };

    let target = currentOpt ? links.find(matchesOption) : null;

    if (!target) {
        target = links.find((link) => {
            try {
                const hrefPath = new URL(link.getAttribute('href'), location.href).pathname.replace(/\/+$/, '');
                return hrefPath === currentPath;
            } catch (e) {
                return false;
            }
        });
    }

    if (target) {
        target.classList.add('active');
        target.setAttribute('aria-current', 'page');
    }
})();

/* Top menus and More menus: one open at a time */
(() => {
    const topMenus = Array.from(document.querySelectorAll('.topnav .menu'));
    const moreMenuSelector = '.controller-more__menu';
    const viewportWidth = () => Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);

    const topButton = (menu) => menu.querySelector('.menu__button');
    const topPanel = (menu) => menu.querySelector('.menu__panel');
    const moreButton = (menu) => {
        if (!menu || !menu.id) return null;
        return document.querySelector(`.controller-more__toggle[aria-controls="${CSS.escape(menu.id)}"]`);
    };

    const placeTopPanel = (menu) => {
        const panel = topPanel(menu);
        if (!panel) return;
        panel.classList.remove('is-flip');
        if (panel.getBoundingClientRect().right > viewportWidth() - 8) {
            panel.classList.add('is-flip');
        }
    };

    const closeTop = (menu) => {
        const button = topButton(menu);
        const panel = topPanel(menu);
        if (button) button.setAttribute('aria-expanded', 'false');
        if (panel) panel.hidden = true;
    };

    const closeMore = (menu) => {
        menu.hidden = true;
        const button = moreButton(menu);
        if (button) button.setAttribute('aria-expanded', 'false');
    };

    const closeAllMore = (except = null) => {
        document.querySelectorAll(`${moreMenuSelector}:not([hidden])`).forEach((menu) => {
            if (menu !== except) closeMore(menu);
        });
    };

    const closeAll = () => {
        topMenus.forEach(closeTop);
        closeAllMore();
    };

    const openTop = (menu) => {
        closeAll();
        const button = topButton(menu);
        const panel = topPanel(menu);
        if (button) button.setAttribute('aria-expanded', 'true');
        if (panel) {
            panel.hidden = false;
            placeTopPanel(menu);
        }
    };

    document.addEventListener('click', (event) => {
        const topToggle = event.target.closest('.topnav .menu__button');
        if (topToggle) {
            event.preventDefault();
            const menu = topToggle.closest('.menu');
            const panel = menu && topPanel(menu);
            if (!menu || !panel) return;
            if (panel.hidden) openTop(menu);
            else closeTop(menu);
            return;
        }

        const moreToggle = event.target.closest('.controller-more__toggle');
        if (moreToggle) {
            event.preventDefault();
            const id = moreToggle.getAttribute('aria-controls');
            const menu = id ? document.getElementById(id) : null;
            if (!menu) return;
            if (menu.hidden) {
                closeAll();
                menu.hidden = false;
                moreToggle.setAttribute('aria-expanded', 'true');
            } else {
                closeMore(menu);
            }
            return;
        }

        if (!event.target.closest('.topnav .menu, .controller-more')) closeAll();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAll();
    });

    window.addEventListener('resize', () => {
        topMenus.forEach((menu) => {
            const panel = topPanel(menu);
            if (panel && !panel.hidden) placeTopPanel(menu);
        });
    });
})();

/* Fade the edge of a section-link row when it scrolls */
(() => {
    const navs = Array.from(document.querySelectorAll('.sectionnav'));
    if (!navs.length) return;

    const update = () => {
        navs.forEach((nav) => {
            const overflows = nav.scrollWidth > nav.clientWidth + 1;
            const atEnd = nav.scrollLeft + nav.clientWidth >= nav.scrollWidth - 2;
            nav.classList.toggle('is-overflowing', overflows);
            nav.classList.toggle('is-scrolled-end', atEnd);
        });
    };

    navs.forEach((nav) => nav.addEventListener('scroll', update, { passive: true }));
    window.addEventListener('resize', update);
    update();
})();

/* Record lists opt in with data-record-list. Confirm links opt in with data-confirm. */
(() => {
    const lists = Array.from(document.querySelectorAll('[data-record-list]'));

    const apply = (root) => {
        const input = root.querySelector('.record-list__search');
        if (!input) return;
        const count = root.querySelector('.record-list__count');
        const empty = root.querySelector('.record-list__nomatch');
        const total = root.querySelectorAll('tr[data-search]').length;
        const q = (input.value || '').trim().toLowerCase();
        let shown = 0;
        root.querySelectorAll('tr[data-search]').forEach((row) => {
            const hit = q === '' || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
            row.hidden = !hit;
            if (hit) shown += 1;
        });
        root.querySelectorAll('tbody[data-group]').forEach((group) => {
            const visible = group.querySelectorAll('tr[data-search]:not([hidden])').length;
            group.hidden = visible === 0;
            const badge = group.querySelector('.record-list__badge');
            if (badge) badge.textContent = String(visible);
        });
        if (count) count.textContent = q === '' ? String(total) : (shown + ' / ' + total);
        if (empty) empty.hidden = shown !== 0;
    };

    const place = (menu) => {
        const panel = menu.querySelector('.rowmenu__panel');
        const summary = menu.querySelector('summary');
        if (!panel || !summary) return;
        const rect = summary.getBoundingClientRect();
        panel.style.top = (rect.bottom + 4) + 'px';
        panel.style.right = Math.max(8, window.innerWidth - rect.right) + 'px';
    };

    lists.forEach((root) => {
        const input = root.querySelector('.record-list__search');
        if (input) input.addEventListener('input', () => apply(root));
        root.querySelectorAll('details.rowmenu').forEach((menu) => {
            menu.addEventListener('toggle', () => {
                if (!menu.open) return;
                root.querySelectorAll('details.rowmenu[open]').forEach((other) => {
                    if (other !== menu) other.open = false;
                });
                place(menu);
            });
        });
    });

    document.addEventListener('click', (event) => {
        const confirmNode = event.target.closest('[data-confirm]');
        if (confirmNode) {
            const message = confirmNode.getAttribute('data-confirm') || '';
            if (message !== '' && !window.confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }
        }
        if (event.target.closest('.rowmenu')) return;
        document.querySelectorAll('[data-record-list] details.rowmenu[open]').forEach((menu) => {
            menu.open = false;
        });
    });

    window.addEventListener('resize', () => {
        document.querySelectorAll('[data-record-list] details.rowmenu[open]').forEach(place);
    });
})();

/* Mark inline .URL actions that match the current ?option= */
(() => {
    const norm = (value) => (value || '').toString().trim().toLowerCase();
    const links = Array.from(document.querySelectorAll('.URL[href]'));
    if (!links.length) return;

    links.forEach((link) => link.classList.remove('is-current'));

    const pageOpt = norm(new URL(location.href).searchParams.get('option'));
    if (!pageOpt) return;

    links.forEach((link) => {
        const fromAttr = norm(link.getAttribute('data-option'));
        let fromHref = '';
        try {
            fromHref = norm(new URL(link.getAttribute('href'), location.href).searchParams.get('option'));
        } catch (e) {
            fromHref = '';
        }
        if (fromAttr === pageOpt || fromHref === pageOpt) link.classList.add('is-current');
    });
})();
