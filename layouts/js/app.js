/* Adlexone layout behaviour */

(() => {
    document.querySelectorAll('[data-toast]').forEach((toast) => {
        const close = () => toast.remove();
        toast.querySelector('[data-toast-close]')?.addEventListener('click', close);
        window.setTimeout(close, 5200);
    });
})();

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
        const chip = root.getAttribute('data-chip') || '';
        let shown = 0;
        root.querySelectorAll('tr[data-search]').forEach((row) => {
            const hit = (q === '' || (row.getAttribute('data-search') || '').indexOf(q) !== -1)
                && (chip === '' || (row.getAttribute('data-stand') || '') === chip);
            row.hidden = !hit;
            if (hit) shown += 1;
        });
        root.querySelectorAll('tbody[data-group]').forEach((group) => {
            const visible = group.querySelectorAll('tr[data-search]:not([hidden])').length;
            group.hidden = visible === 0;
            const badge = group.querySelector('.record-list__badge');
            if (badge) badge.textContent = String(visible);
        });
        if (count) count.textContent = q === '' && chip === '' ? String(total) : (shown + ' / ' + total);
        if (empty) empty.hidden = shown !== 0;
    };

    const place = (menu) => {
        const panel = menu.querySelector('.rowmenu__panel');
        const summary = menu.querySelector('summary');
        if (!panel || !summary) return;
        const rect = summary.getBoundingClientRect();
        const viewportW = document.documentElement.clientWidth || window.innerWidth;
        const viewportH = document.documentElement.clientHeight || window.innerHeight;
        const maxWidth = Math.max(160, viewportW - 16);
        panel.style.maxWidth = maxWidth + 'px';
        const width = Math.min(panel.offsetWidth || 180, maxWidth);
        const height = panel.offsetHeight || 0;
        let left = rect.right - width;
        if (left < 8) left = 8;
        if (left + width > viewportW - 8) left = Math.max(8, viewportW - width - 8);
        let top = rect.bottom + 4;
        const roomBelow = viewportH - 8 - top;
        const roomAbove = rect.top - 8;
        if (height > roomBelow && roomAbove > roomBelow) {
            top = Math.max(8, rect.top - height - 4);
        }
        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
        panel.style.right = 'auto';
        const placed = panel.getBoundingClientRect();
        if (Math.abs(placed.left - left) > 1 || Math.abs(placed.top - top) > 1) {
            panel.style.left = (left - (placed.left - left)) + 'px';
            panel.style.top = (top - (placed.top - top)) + 'px';
        }
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

    /* Links that change data (option=add_, update_, delete_, move_) are sent as a POST with the page token. */
    const postLink = (link) => {
        let option = '';
        try {
            option = new URL(link.getAttribute('href'), location.href).searchParams.get('option') || '';
        } catch (e) {
            return false;
        }
        if (!/^(add|update|delete|move)_[a-z_]+$/.test(option)) return false;
        const meta = document.querySelector('meta[name="csrf-token"]');
        const form = document.createElement('form');
        form.method = 'post';
        form.action = link.href;
        form.hidden = true;
        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_csrf';
        token.value = meta ? meta.content : '';
        form.appendChild(token);
        document.body.appendChild(form);
        form.submit();
        return true;
    };

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
        const link = event.target.closest('a[href]');
        if (link && !event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && postLink(link)) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }
        if (event.target.closest('.rowmenu')) return;
        document.querySelectorAll('[data-record-list] details.rowmenu[open]').forEach((menu) => {
            menu.open = false;
        });
    });

    const reposition = () => {
        document.querySelectorAll('[data-record-list] details.rowmenu[open]').forEach(place);
    };
    window.addEventListener('resize', reposition);
    window.addEventListener('scroll', reposition, true);
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

/* Forms: inline validation behind the legacy fieldCheck() call, required markers, password reveal, busy submit.
   Screens still attach onclick="return fieldCheck(header, tests, names, errors, requiredMessages, required)". */
(() => {
    const trim = (value) => (value || '').trim();

    const validIsoDate = (value) => {
        const match = trim(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return false;
        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(year, month - 1, day);
        return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
    };

    const validDate = (value, format) => {
        if (validIsoDate(value)) return true;
        const order = format === 'MDY' ? 'MDY' : 'DMY';
        const parts = trim(value).split(/[/-]/);
        if (parts.length !== 3 || parts.some((part) => !/^\d{1,4}$/.test(part))) return false;
        const [first, second, third] = parts.map(Number);
        let year = third;
        if (parts[2].length <= 2) year += year > 50 ? 1900 : 2000;
        const month = order === 'DMY' ? second : first;
        const day = order === 'DMY' ? first : second;
        const date = new Date(year, month - 1, day);
        return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
    };

    const validUrl = (value) => {
        const text = trim(value);
        if (!text) return false;
        try {
            const href = /^[a-z][a-z0-9+.-]*:/i.test(text) ? text : `http://${text}`;
            const parsed = new URL(href);
            return parsed.hostname.includes('.');
        } catch (e) {
            return false;
        }
    };

    const validTime = (value) => {
        const match = trim(value).match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
        return !!match && Number(match[1]) <= 23 && Number(match[2]) <= 59 && (match[3] === undefined || Number(match[3]) <= 59);
    };

    const validIp = (value) => {
        const parts = trim(value).split('.');
        return parts.length === 4 && parts.every((part) => /^\d{1,3}$/.test(part) && Number(part) <= 255)
            && Number(parts[0]) > 0;
    };

    const validEmail = (value) => trim(value).split(',').every((address) => (
        /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/.test(trim(address))
    ));

    const isInvalid = (type, value) => {
        const [kind, ...options] = (type || '').split(',');
        switch (kind) {
            case 'numeric': return isNaN(value);
            case 'string': return /[^a-z\s'-.]/i.test(trim(value));
            case 'alphanumeric': return /\W/.test(value);
            case 'date': return !validDate(value, options[0]);
            case 'time': return !validTime(value);
            case 'datetime': {
                if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(trim(value))) {
                    const [day, time] = trim(value).split('T');
                    return !validIsoDate(day) || !validTime(time);
                }
                const [day = '', time = ''] = trim(value).split(/\s+/);
                return !validDate(day, options[0]) || !validTime(time);
            }
            case 'ip': return !validIp(value);
            case 'email': return !validEmail(value);
            case 'url': return !validUrl(value);
            case 'regexp':
                try { return !new RegExp(options.join(',')).test(value); } catch (e) { return false; }
            default: return false;
        }
    };

    const controlOf = (name) => (name ? document.getElementsByName(name)[0] : undefined);
    const labelOf = (control) => (
        control.id ? document.querySelector(`label[for="${CSS.escape(control.id)}"]`) : null
    );

    const describedBy = (control, id, on) => {
        const ids = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter((x) => x && x !== id);
        if (on) ids.push(id);
        if (ids.length) control.setAttribute('aria-describedby', ids.join(' '));
        else control.removeAttribute('aria-describedby');
    };

    const clearError = (control) => {
        const form = control.form;
        control.removeAttribute('aria-invalid');
        const id = control.dataset.errorId;
        if (id) {
            const note = document.getElementById(id);
            if (note) note.remove();
            describedBy(control, id, false);
            delete control.dataset.errorId;
        }
        const summary = form && form.querySelector('.form-errors');
        if (summary) {
            summary.querySelectorAll('li').forEach((item) => {
                if (item.dataset.for === control.id) item.remove();
            });
            if (!form.querySelector('[aria-invalid="true"]')) summary.remove();
        }
    };

    const showError = (control, message) => {
        clearError(control);
        const id = `error-${control.id || control.name}`;
        const note = document.createElement('p');
        note.className = 'field-error';
        note.id = id;
        note.textContent = message;
        const host = control.closest('.input-affix') || control;
        host.insertAdjacentElement('afterend', note);
        control.setAttribute('aria-invalid', 'true');
        control.dataset.errorId = id;
        describedBy(control, id, true);
    };

    const showSummary = (form, header, problems) => {
        const old = form.querySelector('.form-errors');
        if (old) old.remove();
        const summary = document.createElement('div');
        summary.className = 'form-errors';
        summary.setAttribute('role', 'alert');
        summary.tabIndex = -1;
        const title = document.createElement('p');
        title.className = 'form-errors__title';
        title.textContent = header;
        const list = document.createElement('ul');
        problems.forEach(({ control, message }) => {
            const item = document.createElement('li');
            item.dataset.for = control.id;
            const link = document.createElement('a');
            link.href = `#${control.id}`;
            link.textContent = message;
            link.addEventListener('click', (event) => {
                event.preventDefault();
                control.focus();
            });
            item.appendChild(link);
            list.appendChild(item);
        });
        summary.append(title, list);
        const anchor = form.querySelector('.form-grid') || form.firstElementChild;
        form.insertBefore(summary, anchor);
    };

    window.fieldCheck = (header, testTypes, names, errorMessages, requiredMessages, required) => {
        const problems = [];
        let form = null;
        (names || []).forEach((name, i) => {
            const control = controlOf(name);
            if (!control) return;
            form = form || control.form;
            clearError(control);
            const value = control.value || '';
            let message = '';
            if (required && required[i] && trim(value) === '') {
                message = (requiredMessages && requiredMessages[i]) || 'This field is required';
            } else if (trim(value) !== '' && isInvalid(testTypes && testTypes[i], value)) {
                message = (errorMessages && errorMessages[i]) || 'This value is not valid';
            }
            if (message) problems.push({ control, message });
        });

        if (!problems.length) return true;
        problems.forEach(({ control, message }) => showError(control, message));
        if (form) showSummary(form, header, problems);
        problems[0].control.focus();
        problems[0].control.scrollIntoView({ block: 'center', behavior: 'smooth' });
        return false;
    };

    const markServerErrors = () => {
        const links = document.querySelectorAll('.form-errors a[data-field]');
        let first = null;
        links.forEach((link) => {
            const control = controlOf(link.dataset.field);
            if (!control) return;
            if (!control.id) control.id = `field-${link.dataset.field}`;
            link.setAttribute('href', `#${control.id}`);
            link.addEventListener('click', (event) => {
                event.preventDefault();
                control.focus();
            });
            link.closest('li').dataset.for = control.id;
            showError(control, link.textContent || '');
            first = first || control;
        });
        if (first) {
            first.focus({ preventScroll: true });
            first.scrollIntoView({ block: 'center' });
        }
    };
    markServerErrors();

    document.addEventListener('input', (event) => {
        const control = event.target;
        if (control instanceof HTMLElement && control.getAttribute('aria-invalid') === 'true') clearError(control);
    });
    document.addEventListener('change', (event) => {
        const control = event.target;
        if (control instanceof HTMLElement && control.getAttribute('aria-invalid') === 'true') clearError(control);
    });

    /* Mark the fields a legacy fieldCheck() call will insist on, by running its arguments through a stub. */
    const markRequired = (control) => {
        control.setAttribute('aria-required', 'true');
        const label = labelOf(control);
        if (label) label.classList.add('is-required');
    };

    document.querySelectorAll('form [onclick*="fieldCheck("]').forEach((button) => {
        let args = null;
        try {
            new Function('fieldCheck', button.getAttribute('onclick'))((...captured) => {
                args = captured;
                return true;
            });
        } catch (e) {
            return;
        }
        if (!args) return;
        const [, , names, , , required] = args;
        (names || []).forEach((name, i) => {
            const control = controlOf(name);
            if (control && required && required[i]) markRequired(control);
        });
    });

    document.querySelectorAll('input[required], select[required], textarea[required]').forEach(markRequired);

    /* Password reveal */
    document.querySelectorAll('form input[type="password"]').forEach((input) => {
        if (input.closest('.input-affix')) return;
        const wrap = document.createElement('span');
        wrap.className = 'input-affix';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'input-affix__toggle';
        toggle.textContent = 'Show';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.setAttribute('aria-pressed', 'false');
        toggle.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
        wrap.appendChild(toggle);
    });

    /* Busy submit: one request per form. The button keeps its value, so controllers still see it. */
    const settle = (form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('.is-busy').forEach((node) => node.classList.remove('is-busy'));
        form.querySelectorAll('.btn[aria-disabled]').forEach((node) => node.removeAttribute('aria-disabled'));
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        setTimeout(() => {
            if (event.defaultPrevented) return;
            form.dataset.submitting = '1';
            form.setAttribute('aria-busy', 'true');
            const submitter = event.submitter || form.querySelector('[type="submit"]');
            if (submitter) submitter.classList.add('is-busy');
            form.querySelectorAll('.btn').forEach((node) => node.setAttribute('aria-disabled', 'true'));
            setTimeout(() => settle(form), 15000);
        }, 0);
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) document.querySelectorAll('form[data-submitting]').forEach(settle);
    });
})();

/* Record tools: Access, Files, and Notes open as dialogs. */
(() => {
    const asDialog = (node) => (node instanceof HTMLDialogElement ? node : null);
    const open = (dialog) => {
        if (!dialog || dialog.open) return;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    };
    const metaOf = (dialog) => document.querySelector('[aria-controls="' + CSS.escape(dialog.id) + '"] .record-tool__meta');
    const syncMeta = (dialog) => {
        const meta = metaOf(dialog);
        if (!meta) return;
        const stored = meta.getAttribute('data-meta') || '';
        if (dialog.id === 'sheet-pop-files') {
            const input = dialog.querySelector('input[type="file"]');
            const file = input && input.files && input.files[0];
            meta.textContent = file ? file.name : stored;
        }
        if (dialog.id === 'sheet-pop-notes') {
            const area = dialog.querySelector('textarea[name="log_entry"]');
            const text = area ? area.value.replace(/\s+/g, ' ').trim() : '';
            meta.textContent = text === '' ? stored : (text.length <= 42 ? text : '1 note');
        }
        if (dialog.id === 'sheet-pop-access' && !dialog.querySelector('form')) {
            const boxes = dialog.querySelectorAll('input[type="checkbox"]');
            if (!boxes.length) return;
            const checked = dialog.querySelectorAll('input[type="checkbox"]:checked').length;
            meta.textContent = checked === 0 ? 'Not shared' : (checked === 1 ? '1 group' : checked + ' groups');
        }
    };

    document.querySelectorAll('[data-sheet-pop]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = asDialog(document.getElementById(button.getAttribute('aria-controls') || ''));
            if (dialog) open(dialog);
        });
    });
    document.querySelectorAll('[data-sheet-pop-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = asDialog(button.closest('dialog'));
            if (dialog) dialog.close();
        });
    });
    document.querySelectorAll('dialog.sheet-pop').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('input', () => syncMeta(dialog));
        dialog.addEventListener('change', () => syncMeta(dialog));
        dialog.addEventListener('close', () => syncMeta(dialog));
    });

    const sheet = new URLSearchParams(location.search).get('sheet');
    if (sheet === 'access' || sheet === 'files' || sheet === 'notes') {
        const dialog = asDialog(document.getElementById('sheet-pop-' + sheet));
        if (dialog) open(dialog);
        const url = new URL(location.href);
        url.searchParams.delete('sheet');
        history.replaceState(null, '', url.pathname + url.search + url.hash);
    }
})();
