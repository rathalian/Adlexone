/* Application search: field filters are added and removed in the page, and each row follows the field's type. */
(() => {
    const form = document.querySelector('[data-finder]');
    if (!form) return;

    const fields = JSON.parse(form.getAttribute('data-fields') || '[]');
    const ops = JSON.parse(form.getAttribute('data-ops') || '{}');
    const rows = form.querySelector('[data-finder-rows]');
    const template = form.querySelector('template[data-finder-template]');
    const add = form.querySelector('[data-finder-add]');
    let next = rows ? rows.querySelectorAll('[data-finder-row]').length : 0;

    form.classList.add('is-live');

    const control = (name, kind, values, current) => {
        if (kind === 'menu') {
            const select = document.createElement('select');
            select.className = 'select';
            select.name = name;
            select.setAttribute('aria-label', 'Value');
            values.forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                if (value === current) option.selected = true;
                select.appendChild(option);
            });
            return select;
        }
        const input = document.createElement('input');
        input.name = name;
        input.setAttribute('aria-label', 'Value');
        input.autocomplete = 'off';
        if (kind === 'check') {
            input.type = 'hidden';
            input.value = '';
            return input;
        }
        input.className = 'input';
        input.type = kind === 'number' ? 'number' : (kind === 'date' ? 'date' : 'text');
        if (kind === 'number') input.step = 'any';
        input.value = current || '';
        return input;
    };

    const adapt = (row) => {
        const picked = row.querySelector('[data-finder-field]');
        const op = row.querySelector('[data-finder-op]');
        const holder = row.querySelector('[data-finder-value]');
        const field = fields.find((f) => String(f.id) === picked.value);
        const kind = field ? field.kind : 'text';
        const valueName = picked.name.replace('[field]', '[value]');

        const before = op.value;
        op.innerHTML = '';
        (ops[kind] || ops.text).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            if (value === before) option.selected = true;
            op.appendChild(option);
        });

        const old = holder.querySelector('input, select');
        const keep = old && old.type !== 'hidden' ? old.value : '';
        holder.innerHTML = '';
        holder.appendChild(control(valueName, kind, field ? field.values : [], keep));
        row.setAttribute('data-kind', kind);
    };

    if (rows) {
        rows.addEventListener('change', (event) => {
            if (event.target.matches('[data-finder-field]')) {
                adapt(event.target.closest('[data-finder-row]'));
            }
        });
        rows.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-finder-remove]');
            if (!remove) return;
            remove.closest('[data-finder-row]').remove();
            if (add) add.focus();
        });
    }

    if (add && template && rows) {
        add.addEventListener('click', () => {
            const holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.split('__N__').join(String(next));
            next += 1;
            const row = holder.firstElementChild;
            rows.appendChild(row);
            row.querySelector('[data-finder-field]').focus();
        });
    }

    const plain = Array.from(form.querySelectorAll('.finder__bar input, .finder__filters select'));
    form.addEventListener('submit', () => {
        if (rows) {
            rows.querySelectorAll('[data-finder-row]').forEach((row) => {
                if (!row.querySelector('[data-finder-field]').value) row.remove();
            });
        }
        plain.forEach((control) => {
            if (control.value === '') control.disabled = true;
        });
    });
    window.addEventListener('pageshow', () => {
        plain.forEach((control) => { control.disabled = false; });
    });
})();
