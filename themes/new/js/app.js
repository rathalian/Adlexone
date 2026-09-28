/* =========================================================
   Adlexone Core JS — Clean (2025-10-16)
   Includes:
     • Align brand underline with stylised “A”
     • Left-nav persistence
     • Stylised initial without whitespace
     • Prevent top dropdown overflow (auto-flip)
     • NEW: Delegated ARIA toggle for More/Dropdowns
     • NEW: .URL active state via ?option= and first-letter styling
   ========================================================= */

/* Utility */
const mqDesktop = window.matchMedia('(min-width: 961px)');
const isMobile = () => !mqDesktop.matches;

/* 0) Mark layout if sidebar exists */
(() => {
    const wrap = document.querySelector('.wrap');
    const sidebar = document.querySelector('.sidebar');
    if (wrap && sidebar) wrap.classList.add('has-sidebar');
})();

/* 1) Left-nav persistence — prefer ?option=, then path */
(() => {
    const norm = s => (s || '').toString().trim().toLowerCase();
    const links = Array.from(document.querySelectorAll('.sidebar .navbtn[href]'));
    if (!links.length) return;

    // Clear any previous state
    links.forEach(a => { a.classList.remove('active'); a.removeAttribute('aria-current'); });

    const url = new URL(location.href);
    const currentOpt  = norm(url.searchParams.get('option'));
    const currentPath = url.pathname.replace(/\/+$/,'');

    let target = null;

    // 1) Use ?option= when present
    if (currentOpt){
        target = links.find(a => {
            const optAttr = norm(a.getAttribute('data-option'));
            let linkOpt = '';
            try { linkOpt = norm(new URL(a.getAttribute('href'), location.href).searchParams.get('option')); } catch(e){}
            return optAttr === currentOpt || linkOpt === currentOpt;
        });
    }

    // 2) Fallback to path
    if (!target){
        target = links.find(a => {
            try{
                const hrefPath = new URL(a.getAttribute('href'), location.href).pathname.replace(/\/+$/,'');
                return hrefPath === currentPath;
            }catch(e){ return false; }
        });
    }

    if (target){
        target.classList.add('active');
        target.setAttribute('aria-current','page');
    }
})();

/* 2) Stylised initial for selected left-nav items — rebuild label to remove spaces */
(() => {
    const selected = document.querySelectorAll('.sidebar .navbtn[aria-current="page"], .sidebar .navbtn.active');
    selected.forEach(a => {
        if (a.querySelector('.navcap')) return;

        // Build clean label from anchor's visible text (icons ignored by textContent)
        const full = (a.textContent || '').replace(/\s+/g, ' ').trim();
        if (!full) return;

        const first = full.charAt(0);
        const rest  = full.slice(1);

        // Remove existing text-bearing nodes (keep pure icon nodes)
        const toRemove = [];
        a.childNodes.forEach(n => {
            if (n.nodeType === 3 && n.nodeValue.trim()) toRemove.push(n);
            else if (n.nodeType === 1 && n.textContent.trim()) toRemove.push(n);
        });
        toRemove.forEach(n => n.remove());

        // Append as cap + rest (no whitespace gap)
        const cap = document.createElement('span');
        cap.className = 'navcap';
        cap.textContent = first;
        a.append(cap, document.createTextNode(rest));
    });
})();

/* 3) Brand underline alignment — anchor to .brandwrap */
(() => {
    const row  = document.querySelector('.brandrow');
    const wrap = document.querySelector('.brandwrap');
    if (!row || !wrap) return;

    const update = () => {
        const r = row.getBoundingClientRect();
        const w = wrap.getBoundingClientRect();
        const left = Math.max(0, Math.round(w.left - r.left)); // px from brandrow left edge
        row.style.setProperty('--brandline-left', left + 'px');
    };

    update();
    window.addEventListener('resize', update);
    window.addEventListener('load', update);
})();

/* 4) Top menus — click to open, one at a time, flip near the right edge */
(() => {
    const menus = Array.from(document.querySelectorAll('.topnav .menu'));
    if (!menus.length) return;

    const vw = () => Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
    const buttonOf = (menu) => menu.querySelector('.menu__button');
    const panelOf = (menu) => menu.querySelector('.menu__panel');

    const place = (menu) => {
        const panel = panelOf(menu);
        if (!panel) return;
        panel.classList.remove('is-flip');
        const rect = panel.getBoundingClientRect();
        if (rect.right > vw() - 8) panel.classList.add('is-flip');
    };

    const closeMenu = (menu) => {
        const button = buttonOf(menu);
        const panel = panelOf(menu);
        if (button) button.setAttribute('aria-expanded', 'false');
        if (panel) panel.hidden = true;
    };

    const openMenu = (menu) => {
        menus.forEach((other) => { if (other !== menu) closeMenu(other); });
        const button = buttonOf(menu);
        const panel = panelOf(menu);
        if (button) button.setAttribute('aria-expanded', 'true');
        if (panel) {
            panel.hidden = false;
            place(menu);
        }
    };

    menus.forEach((menu) => {
        const button = buttonOf(menu);
        if (!button) return;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const panel = panelOf(menu);
            if (panel && !panel.hidden) closeMenu(menu);
            else openMenu(menu);
        });
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('.topnav .menu')) return;
        menus.forEach(closeMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') menus.forEach(closeMenu);
    });

    window.addEventListener('resize', () => {
        menus.forEach((menu) => {
            const panel = panelOf(menu);
            if (panel && !panel.hidden) place(menu);
        });
    });
})();

/* Section links — fade the edge when the row scrolls */
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

/* 5) NEW — Delegated toggle for any [aria-controls] button (More/Dropdowns) */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('button[aria-controls]');
    if (!btn) return;
    const id = btn.getAttribute('aria-controls');
    if (!id) return;
    const region = document.getElementById(id);
    if (!region) return;

    const expanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', String(!expanded));
    region.hidden = expanded;
});

/* 6) NEW — Mark .URL inline action links current via ?option= and stylise first letter */
(() => {
    const norm = s => (s || '').toString().trim().toLowerCase();
    const links = Array.from(document.querySelectorAll('.URL[href]'));
    if (!links.length) return;

    // Clear previous state
    links.forEach(a => a.classList.remove('is-current'));

    const pageOpt = norm(new URL(location.href).searchParams.get('option'));
    if (!pageOpt) return;

    links.forEach(a => {
        const optAttr = norm(a.getAttribute('data-option'));
        let linkOpt = '';
        try { linkOpt = norm(new URL(a.getAttribute('href'), location.href).searchParams.get('option')); } catch(e){}


        const isMatch = pageOpt && (optAttr === pageOpt || linkOpt === pageOpt);
        if (!isMatch) return;

        a.classList.add('is-current');

        // Ensure first-letter gradient is visible even with icons/markup
        if (a.querySelector('.urlcap')) return;

        const full = (a.textContent || '').replace(/\s+/g, ' ').trim();
        if (!full) return;

        const first = full.charAt(0);
        const rest  = full.slice(1);

        // Remove text-bearing nodes; keep pure icon nodes
        const toRemove = [];
        a.childNodes.forEach(n => {
            if (n.nodeType === 3 && n.nodeValue.trim()) toRemove.push(n);
            else if (n.nodeType === 1 && n.textContent.trim()) toRemove.push(n);
        });
        toRemove.forEach(n => n.remove());

        const cap = document.createElement('span');
        cap.className = 'urlcap';
        cap.textContent = first;

        const label = document.createElement('span');
        label.className = 'urllabel';
        label.append(cap, document.createTextNode(rest));
        a.append(label);
    });
})();

/* === Adlexone: Content Menu toggle + ARIA (bundle) === */
(function(){
    const trigger = document.querySelector('.content-menu-link, .content-menu-trigger');
    const sidebar = document.querySelector('.wrap.has-sidebar .sidebar, .sidebar');
    if(!trigger || !sidebar) return;
    const body = document.body;

    const open  = () => { body.classList.add('is-side-open'); trigger.setAttribute('aria-expanded','true'); };
    const close = () => { body.classList.remove('is-side-open'); trigger.setAttribute('aria-expanded','false'); };
    const toggle = () => body.classList.contains('is-side-open') ? close() : open();

    trigger.setAttribute('aria-controls','sidebar');
    trigger.setAttribute('aria-expanded','false');
    trigger.addEventListener('click', (e)=>{ e.preventDefault(); toggle(); });

    document.addEventListener('click', (e)=>{
        if(!body.classList.contains('is-side-open')) return;
        const inside = sidebar.contains(e.target) || trigger.contains(e.target);
        if(!inside) close();
    });
    document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') close(); });

    const mqDesktop = window.matchMedia('(min-width: 961px)');
    mqDesktop.addEventListener('change', ()=>{ if(mqDesktop.matches) close(); });
})();

/* --- More menu toggle (accessible, resilient) --- */

(() => {
    const BTN_SEL = '.controller-more__toggle';
    const MENU_SEL = '.controller-more__menu';

    const menuFor = (btn) => {
        if (!btn) return null;
        const id = btn.getAttribute('aria-controls');
        return id ? document.getElementById(id) : null;
    };

    const buttonFor = (menu) => {
        if (!menu || !menu.id) return null;
        return document.querySelector(`${BTN_SEL}[aria-controls="${CSS.escape(menu.id)}"]`);
    };

    const closeMenu = (menu) => {
        if (!menu) return;
        menu.hidden = true;
        const btn = buttonFor(menu);
        if (btn) btn.setAttribute('aria-expanded', 'false');
    };

    const closeAll = (except = null) => {
        document.querySelectorAll(`${MENU_SEL}:not([hidden])`).forEach(m => {
            if (m !== except) closeMenu(m);
        });
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest(BTN_SEL);
        if (btn) {
            // Prevent other delegated/global handlers from also handling this click
            e.preventDefault();
            e.stopPropagation();

            const menu = menuFor(btn);
            if (!menu) return;
            const isOpen = !menu.hidden;
            if (isOpen) {
                closeMenu(menu);
            } else {
                closeAll(null);
                menu.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
            }
            return;
        }

        // Click outside closes any open More menus
        if (!e.target.closest('.controller-more')) {
            closeAll(null);
        }
    }, true);

    // Escape closes
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeAll(null);
    });
})();
