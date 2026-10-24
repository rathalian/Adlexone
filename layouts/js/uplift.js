/* Inlay interface behaviour: colour mode, search and commands, list filters, figures, and tab overflow. */

(() => {
    const root = document.documentElement;
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '');

    document.querySelectorAll('[data-shortcut], .kbd').forEach((node) => {
        if (isMac && node.textContent.trim() === 'Ctrl K') node.textContent = '\u2318K';
    });

    /* Colour mode */
    const systemMode = () => (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const setMode = (mode) => {
        root.setAttribute('data-mode', mode === 'system' ? systemMode() : mode);
        try {
            if (mode === 'system') localStorage.removeItem('inlay.mode');
            else localStorage.setItem('inlay.mode', mode);
        } catch (e) { /* storage is off */ }
    };
    window.inlaySetMode = setMode;
    const toggleMode = () => setMode(root.getAttribute('data-mode') === 'dark' ? 'light' : 'dark');
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-mode-toggle]')) toggleMode();
    });

    /* Figures count up once when the page opens */
    if (!reduced) {
        document.querySelectorAll('[data-count]').forEach((node) => {
            const target = parseInt(node.getAttribute('data-count'), 10);
            if (!Number.isFinite(target) || target < 2) return;
            const started = performance.now();
            const run = (now) => {
                const t = Math.min(1, (now - started) / 700);
                node.textContent = String(Math.round(target * (1 - Math.pow(1 - t, 3))));
                if (t < 1) requestAnimationFrame(run);
            };
            node.textContent = '0';
            requestAnimationFrame(run);
        });
    }

    /* A dismissible panel stays hidden once closed */
    document.querySelectorAll('[data-dismissible]').forEach((panel) => {
        const key = 'inlay.hide.' + panel.getAttribute('data-dismissible');
        try { if (localStorage.getItem(key) === '1') panel.hidden = true; } catch (e) { /* storage is off */ }
        panel.querySelector('[data-dismiss]')?.addEventListener('click', () => {
            panel.hidden = true;
            try { localStorage.setItem(key, '1'); } catch (e) { /* storage is off */ }
        });
    });

    /* Lists that fold on a phone: the button opens the rows marked is-extra */
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-fold-toggle]');
        if (!button) return;
        const holder = button.closest('[data-fold]');
        if (!holder) return;
        const open = holder.classList.toggle('is-open');
        button.setAttribute('aria-expanded', String(open));
        button.textContent = open ? button.getAttribute('data-less') : button.getAttribute('data-more');
    });

    /* Collapsible panels start closed. The browser remembers a panel the person opened. */
    const panels = Array.from(document.querySelectorAll('details[data-remember]'));
    const stored = (panel) => 'inlay.open.' + panel.getAttribute('data-remember');
    const load = (panel, url) => {
        const body = panel.querySelector('[data-lazy-body]');
        if (!body) return;
        const mine = String(Date.now());
        body.setAttribute('data-serial', mine);
        body.setAttribute('aria-busy', 'true');
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => { if (!response.ok) throw new Error('failed'); return response.json(); })
            .then((data) => {
                if (body.getAttribute('data-serial') !== mine) return;
                body.innerHTML = data.html || '';
                panel.setAttribute('data-loaded', '1');
            })
            .catch(() => {
                if (body.getAttribute('data-serial') !== mine) return;
                body.innerHTML = '<p class="timeline__empty">This list could not be loaded. Close this panel and open it again to retry.</p>';
                panel.removeAttribute('data-loaded');
            })
            .finally(() => body.removeAttribute('aria-busy'));
    };
    const ensureLoaded = (panel) => {
        const url = panel.getAttribute('data-lazy');
        if (url && panel.open && !panel.hasAttribute('data-loaded')) {
            panel.setAttribute('data-loaded', 'loading');
            load(panel, url);
        }
    };
    panels.forEach((panel) => {
        try { if (localStorage.getItem(stored(panel)) === '1') panel.open = true; } catch (e) { /* storage is off */ }
        panel.addEventListener('toggle', () => {
            try { localStorage.setItem(stored(panel), panel.open ? '1' : '0'); } catch (e) { /* storage is off */ }
            if (panel.open) {
                if (panel.getAttribute('data-loaded') === null) ensureLoaded(panel);
            } else if (panel.getAttribute('data-loaded') === 'loading') {
                panel.removeAttribute('data-loaded');
            }
        });
    });
    const reveal = (id) => {
        const panel = document.getElementById(id);
        if (!panel || panel.tagName !== 'DETAILS') return false;
        panel.open = true;
        ensureLoaded(panel);
        panel.scrollIntoView({ block: 'start', behavior: reduced ? 'auto' : 'smooth' });
        return true;
    };
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open-panel]');
        if (opener && reveal(opener.getAttribute('data-open-panel'))) event.preventDefault();
        const link = event.target.closest('a[data-lazy-link]');
        const holder = link && link.closest('details[data-lazy]');
        if (holder && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
            event.preventDefault();
            load(holder, link.getAttribute('href'));
        }
    });
    if (window.location.hash.length > 1) reveal(window.location.hash.slice(1));
    panels.forEach(ensureLoaded);

    /* Thin bar across the top while the next page loads */
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
        if (link.target && link.target !== '_self' && link.target !== '_parent') return;
        const href = link.getAttribute('href') || '';
        if (href.startsWith('#') || href.startsWith('javascript:') || link.hasAttribute('download') || link.hasAttribute('data-confirm')) return;
        root.classList.add('is-loading');
    });
    window.addEventListener('pageshow', () => root.classList.remove('is-loading'));
})();

/* Status filters on record lists */
(() => {
    document.querySelectorAll('[data-record-list]').forEach((list) => {
        const rows = Array.from(list.querySelectorAll('tbody tr[data-search]'));
        const input = list.querySelector('.record-list__search');
        if (!input || rows.length < 2) return;

        const tally = new Map();
        rows.forEach((row) => {
            const pill = row.querySelector('td .pill');
            if (!pill) return;
            const label = pill.textContent.trim();
            if (!label) return;
            const key = label.toLowerCase();
            row.setAttribute('data-stand', key);
            const tone = (pill.className.match(/pill--(\w+)/) || [])[1] || 'neutral';
            const entry = tally.get(key) || { label, tone, n: 0 };
            entry.n += 1;
            tally.set(key, entry);
        });
        if (tally.size < 2 || tally.size > 8) return;

        const bar = document.createElement('div');
        bar.className = 'chips';
        bar.setAttribute('role', 'group');
        bar.setAttribute('aria-label', 'Filter by status');
        const make = (key, label, n, tone) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'chip' + (tone ? ' chip--' + tone : '');
            button.setAttribute('data-key', key);
            button.setAttribute('aria-pressed', key === '' ? 'true' : 'false');
            button.innerHTML = '<span class="chip__dot"></span><span class="chip__label"></span><span class="chip__n"></span>';
            button.querySelector('.chip__label').textContent = label;
            button.querySelector('.chip__n').textContent = String(n);
            return button;
        };
        bar.appendChild(make('', 'All', rows.length, ''));
        Array.from(tally.entries()).sort((a, b) => b[1].n - a[1].n).forEach(([key, entry]) => {
            bar.appendChild(make(key, entry.label, entry.n, entry.tone));
        });
        bar.addEventListener('click', (event) => {
            const button = event.target.closest('.chip');
            if (!button) return;
            const key = button.getAttribute('data-key');
            const active = list.getAttribute('data-chip') === key ? '' : key;
            list.setAttribute('data-chip', active);
            bar.querySelectorAll('.chip').forEach((chip) => {
                chip.setAttribute('aria-pressed', String((chip.getAttribute('data-key') || '') === active));
            });
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        list.querySelector('.record-list__bar').insertAdjacentElement('afterend', bar);
    });
})();

/* Section tabs that do not fit move behind More */
(() => {
    const bar = document.querySelector('.sectionbar');
    const nav = bar && bar.querySelector('.sectionnav');
    if (!nav) return;
    const links = Array.from(nav.querySelectorAll('.sectionnav__link'));
    if (links.length < 4) return;

    const more = document.createElement('div');
    more.className = 'sectionmore';
    more.hidden = true;
    more.innerHTML = '<button type="button" class="sectionnav__link sectionmore__button" aria-haspopup="true" aria-expanded="false">'
        + '<span class="sectionnav__label">More</span>'
        + '<svg class="chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none"/></svg></button>'
        + '<div class="sectionmore__panel" hidden></div>';
    bar.appendChild(more);
    const button = more.querySelector('button');
    const panel = more.querySelector('.sectionmore__panel');

    const layout = () => {
        links.forEach((link) => { link.hidden = false; });
        panel.innerHTML = '';
        more.hidden = true;
        nav.style.overflow = 'visible';
        const style = window.getComputedStyle(bar);
        const inner = bar.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        const name = bar.querySelector('.sectionbar__name');
        const nameWidth = name && name.offsetWidth < inner * 0.9 ? name.offsetWidth + 16 : 0;
        const room = inner - nameWidth;
        const moreWidth = 88;
        const widths = links.map((link) => link.offsetWidth + 2);
        const current = links.findIndex((link) => link.getAttribute('aria-current') === 'page');
        const fits = (cut) => {
            let used = cut < links.length ? moreWidth : 0;
            widths.forEach((width, index) => {
                if (index < cut || index === current) used += width;
            });
            return used <= room;
        };
        let cut = links.length;
        while (cut > 0 && !fits(cut)) cut -= 1;
        if (cut < links.length) {
            links.forEach((link, index) => {
                if (index < cut || index === current) return;
                link.hidden = true;
                const copy = link.cloneNode(true);
                copy.hidden = false;
                copy.classList.add('sectionmore__item');
                panel.appendChild(copy);
            });
            more.hidden = panel.children.length === 0;
        }
        nav.style.overflow = '';
    };

    button.addEventListener('click', () => {
        const open = panel.hidden;
        panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', (event) => {
        if (!more.contains(event.target)) {
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        }
    });
    let timer = 0;
    window.addEventListener('resize', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(layout, 80);
    });
    layout();
})();

/* Search and commands */
(() => {
    const box = document.getElementById('palette');
    if (!box) return;
    const input = box.querySelector('.palette__input');
    const list = box.querySelector('.palette__list');
    const none = box.querySelector('.palette__empty');
    const sprite = box.getAttribute('data-sprite') || '';
    const endpoint = box.getAttribute('data-endpoint') || '';
    let commands = null;
    let records = [];
    let shown = [];
    let active = 0;
    let opener = null;
    let timer = 0;
    let serial = 0;

    const modeCommands = () => [
        { kind: 'Actions', label: 'Use light mode', group: 'Appearance', icon: 'ic-sun', run: () => window.inlaySetMode('light') },
        { kind: 'Actions', label: 'Use dark mode', group: 'Appearance', icon: 'ic-moon', run: () => window.inlaySetMode('dark') },
        { kind: 'Actions', label: 'Match the system appearance', group: 'Appearance', icon: 'ic-sliders', run: () => window.inlaySetMode('system') },
        { kind: 'Actions', label: 'Show keyboard shortcuts', group: 'Help', icon: 'ic-search', run: () => window.inlayShortcuts && window.inlayShortcuts() },
    ];
    const kindOrder = ['Records', 'Actions', 'Applications', 'Screens', 'Design', 'Account'];
    const rank = (item) => {
        const at = kindOrder.indexOf(item.kind);
        return at === -1 ? kindOrder.length : at;
    };
    const homeCommand = { kind: 'Actions', label: 'Go to home', group: 'Navigation', icon: 'ic-home', href: './' };

    const load = () => {
        if (commands !== null || !endpoint) return;
        commands = [];
        fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((data) => { commands = data.commands || []; render(); })
            .catch(() => { commands = []; render(); });
    };

    const score = (item, terms) => {
        const hay = (item.label + ' ' + (item.group || '') + ' ' + item.kind).toLowerCase();
        let total = 0;
        for (const term of terms) {
            const at = hay.indexOf(term);
            if (at === -1) return -1;
            total += at === 0 ? 3 : (hay.charAt(at - 1) === ' ' ? 2 : 1);
        }
        return total + (item.label.toLowerCase().startsWith(terms[0]) ? 2 : 0);
    };

    const icon = (name) => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'icon');
        svg.setAttribute('aria-hidden', 'true');
        const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', sprite + '#' + (name || 'ic-grid'));
        svg.appendChild(use);
        return svg;
    };

    const choose = (item) => {
        if (!item) return;
        close();
        if (item.run) item.run();
        else if (item.href) window.location.href = item.href;
    };

    const render = () => {
        const terms = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        const pool = [homeCommand].concat(modeCommands(), commands || []);
        let matches;
        if (terms.length === 0) {
            matches = pool.filter((item) => item.kind !== 'Screens' && item.group !== 'Create' && item.group !== 'Appearance').slice(0, 14);
        } else {
            matches = pool
                .map((item) => ({ item, s: score(item, terms) }))
                .filter((entry) => entry.s >= 0)
                .sort((a, b) => b.s - a.s)
                .map((entry) => entry.item)
                .slice(0, 12);
            matches = records.concat(matches).map((item, index) => ({ item, index }))
                .sort((a, b) => (rank(a.item) - rank(b.item)) || (a.index - b.index))
                .map((entry) => entry.item);
        }
        shown = matches;
        active = Math.min(active, Math.max(0, shown.length - 1));
        list.textContent = '';
        let kind = '';
        shown.forEach((item, index) => {
            if (item.kind !== kind) {
                kind = item.kind;
                const heading = document.createElement('li');
                heading.className = 'palette__heading';
                heading.setAttribute('role', 'presentation');
                heading.textContent = kind;
                list.appendChild(heading);
            }
            const row = document.createElement('li');
            row.className = 'palette__item' + (index === active ? ' is-active' : '');
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', index === active ? 'true' : 'false');
            row.dataset.index = String(index);
            const mark = document.createElement('span');
            mark.className = 'palette__icon';
            mark.appendChild(icon(item.icon));
            const text = document.createElement('span');
            text.className = 'palette__text';
            const label = document.createElement('span');
            label.className = 'palette__label';
            label.textContent = item.label;
            text.appendChild(label);
            if (item.group) {
                const group = document.createElement('span');
                group.className = 'palette__group';
                group.textContent = item.group;
                text.appendChild(group);
            }
            row.appendChild(mark);
            row.appendChild(text);
            list.appendChild(row);
        });
        none.hidden = shown.length !== 0 || commands === null;
    };

    const fetchRecords = () => {
        const q = input.value.trim();
        const mine = ++serial;
        if (q.length < 2 || !endpoint) {
            records = [];
            render();
            return;
        }
        fetch(endpoint + '&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((data) => {
                if (mine !== serial) return;
                records = data.records || [];
                render();
            })
            .catch(() => { /* records stay as they were */ });
    };

    const open = (from) => {
        opener = from || document.activeElement;
        box.hidden = false;
        document.documentElement.classList.add('has-palette');
        input.value = '';
        records = [];
        active = 0;
        load();
        render();
        window.setTimeout(() => input.focus(), 0);
    };
    const close = () => {
        if (box.hidden) return;
        box.hidden = true;
        document.documentElement.classList.remove('has-palette');
        if (opener && opener.focus) opener.focus();
    };

    const move = (step) => {
        if (shown.length === 0) return;
        active = (active + step + shown.length) % shown.length;
        render();
        list.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
    };

    input.addEventListener('input', () => {
        active = 0;
        render();
        window.clearTimeout(timer);
        timer = window.setTimeout(fetchRecords, 160);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
        else if (event.key === 'Enter') { event.preventDefault(); choose(shown[active]); }
    });
    list.addEventListener('click', (event) => {
        const row = event.target.closest('.palette__item');
        if (row) choose(shown[Number(row.dataset.index)]);
    });
    list.addEventListener('mousemove', (event) => {
        const row = event.target.closest('.palette__item');
        if (!row) return;
        const index = Number(row.dataset.index);
        if (index !== active) {
            active = index;
            list.querySelectorAll('.palette__item').forEach((node) => {
                const on = Number(node.dataset.index) === active;
                node.classList.toggle('is-active', on);
                node.setAttribute('aria-selected', String(on));
            });
        }
    });
    box.addEventListener('click', (event) => {
        if (event.target.closest('[data-palette-close]')) close();
    });
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-palette-open]');
        if (trigger) { event.preventDefault(); open(trigger); }
    });
    document.addEventListener('keydown', (event) => {
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test((event.target.tagName || '')) || event.target.isContentEditable;
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if (box.hidden) open(); else close();
        } else if (event.key === '/' && !typing && box.hidden) {
            event.preventDefault();
            open();
        } else if (event.key === 'Escape') {
            close();
        } else if (event.key === 'Tab' && !box.hidden) {
            event.preventDefault();
            input.focus();
        }
    });
})();

/* Keyboard shortcuts: j and k move through a list, Enter opens, n starts a record, g then h goes home, ? lists them */
(() => {
    const rows = () => Array.from(document.querySelectorAll('.record-list tbody tr[data-search], .work-card'))
        .filter((row) => row.offsetParent !== null);
    let current = -1;
    let pending = '';
    let pendingTimer = 0;

    const mark = (index) => {
        const all = rows();
        if (all.length === 0) return;
        current = (index + all.length) % all.length;
        all.forEach((row, at) => row.classList.toggle('is-focused', at === current));
        all[current].scrollIntoView({ block: 'nearest' });
    };
    const openCurrent = () => {
        const row = rows()[current];
        const link = row && row.querySelector('a[href]');
        if (link) window.location.href = link.getAttribute('href');
    };

    let sheet = null;
    const items = [
        ['Ctrl K or /', 'Search and commands'],
        ['?', 'Show this list'],
        ['j and k', 'Move down and up a list'],
        ['Enter', 'Open the highlighted record'],
        ['n', 'Start a new record in this application'],
        ['g then h', 'Go to the home page'],
        ['Esc', 'Close a dialog or the search'],
    ];
    const build = () => {
        sheet = document.createElement('div');
        sheet.className = 'keysheet';
        sheet.setAttribute('role', 'dialog');
        sheet.setAttribute('aria-modal', 'true');
        sheet.setAttribute('aria-label', 'Keyboard shortcuts');
        sheet.hidden = true;
        const backdrop = document.createElement('div');
        backdrop.className = 'palette__backdrop';
        backdrop.addEventListener('click', () => close());
        const box = document.createElement('div');
        box.className = 'keysheet__box';
        const title = document.createElement('h2');
        title.className = 'keysheet__title';
        title.textContent = 'Keyboard shortcuts';
        const list = document.createElement('dl');
        list.className = 'keysheet__list';
        items.forEach(([keys, what]) => {
            const term = document.createElement('dt');
            keys.split(/( or | then | and )/).forEach((part) => {
                if (/^ (or|then|and) $/.test(part)) {
                    term.appendChild(document.createTextNode(part));
                } else {
                    const kbd = document.createElement('kbd');
                    kbd.className = 'kbd';
                    kbd.textContent = part;
                    term.appendChild(kbd);
                }
            });
            const detail = document.createElement('dd');
            detail.textContent = what;
            list.appendChild(term);
            list.appendChild(detail);
        });
        box.appendChild(title);
        box.appendChild(list);
        sheet.appendChild(backdrop);
        sheet.appendChild(box);
        document.body.appendChild(sheet);
    };
    const open = () => {
        if (!sheet) build();
        sheet.hidden = false;
    };
    const close = () => {
        if (sheet) sheet.hidden = true;
    };
    window.inlayShortcuts = open;

    document.addEventListener('keydown', (event) => {
        if (event.metaKey || event.ctrlKey || event.altKey) return;
        const target = event.target;
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName || '') || target.isContentEditable;
        if (event.key === 'Escape') { close(); return; }
        if (typing) return;
        if (document.getElementById('palette') && !document.getElementById('palette').hidden) return;
        const key = event.key;
        if (pending === 'g') {
            pending = '';
            window.clearTimeout(pendingTimer);
            if (key === 'h') { event.preventDefault(); window.location.href = './'; }
            return;
        }
        if (key === '?') { event.preventDefault(); if (sheet && !sheet.hidden) close(); else open(); }
        else if (key === 'j') { event.preventDefault(); mark(current + 1); }
        else if (key === 'k') { event.preventDefault(); mark(current < 0 ? 0 : current - 1); }
        else if (key === 'Enter' && current >= 0 && !(target.tagName === 'A' || target.tagName === 'BUTTON')) { openCurrent(); }
        else if (key === 'n') {
            const create = document.querySelector('.apphero__action');
            if (create) { event.preventDefault(); window.location.href = create.getAttribute('href'); }
        } else if (key === 'g') {
            pending = 'g';
            pendingTimer = window.setTimeout(() => { pending = ''; }, 900);
        }
    });
})();

/* Long menus: a filter box, and the applications used most recently listed first */
(() => {
    const filters = document.querySelectorAll('[data-menu-filter]');
    if (filters.length === 0) return;
    const key = 'inlay.recentApps';
    const read = () => {
        try { return JSON.parse(localStorage.getItem(key) || '[]').filter((href) => typeof href === 'string'); } catch (e) { return []; }
    };

    filters.forEach((input) => {
        const panel = input.closest('.menu__panel');
        const menu = input.closest('.menu');
        const items = () => Array.from(panel.querySelectorAll('.menu__item'));

        const recent = read().slice(0, 3);
        const first = panel.querySelector('.menu__filter');
        const placed = recent.map((href) => items().find((item) => item.getAttribute('href') === href)).filter(Boolean);
        if (placed.length > 0) {
            const heading = document.createElement('div');
            heading.className = 'menu__heading';
            heading.textContent = 'Recent';
            first.after(heading);
            let after = heading;
            placed.forEach((item) => { after.after(item); after = item; });
            const rest = document.createElement('div');
            rest.className = 'menu__heading';
            rest.textContent = 'All applications';
            after.after(rest);
        }

        panel.addEventListener('click', (event) => {
            const link = event.target.closest('.menu__item');
            if (!link) return;
            const href = link.getAttribute('href') || '';
            if (href === '') return;
            const next = [href].concat(read().filter((entry) => entry !== href)).slice(0, 6);
            try { localStorage.setItem(key, JSON.stringify(next)); } catch (e) { /* storage is off */ }
        });

        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            items().forEach((item) => {
                const name = (item.querySelector('.menu__item-text > span') || item).textContent.toLowerCase();
                item.hidden = term !== '' && !name.includes(term);
            });
            panel.querySelectorAll('.menu__heading').forEach((heading) => { heading.hidden = term !== ''; });
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                const match = items().find((item) => !item.hidden);
                if (match) window.location.href = match.getAttribute('href');
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                items().find((item) => !item.hidden)?.focus();
            }
        });
        menu.querySelector('.menu__button')?.addEventListener('click', () => {
            window.setTimeout(() => { if (!panel.hidden) input.focus(); }, 0);
        });
    });
})();
