/**
 * The guru's attendance sheet (pages/guru/attendance): live counters per status, row colouring, and a
 * name/NIS filter so a long class stays workable. Loaded only on pages that contain the form.
 */

const STATUSES = ['hadir', 'izin', 'sakit', 'alpha'];

function rowStatus(row) {
    return row.querySelector('input[type="radio"]:checked')?.value ?? '';
}

function updateCounters(form) {
    const counters = form.querySelector('[data-attendance-counters]');

    if (!counters) {
        return;
    }

    const rows = [...form.querySelectorAll('[data-attendance-row]')];
    const counts = Object.fromEntries(STATUSES.map((status) => [status, 0]));
    let empty = 0;

    rows.forEach((row) => {
        const status = rowStatus(row);
        row.dataset.status = status;

        if (status) {
            counts[status] += 1;
        } else {
            empty += 1;
        }
    });

    STATUSES.forEach((status) => {
        const cell = counters.querySelector(`[data-count="${status}"]`);

        if (cell) {
            cell.textContent = String(counts[status]);
        }
    });

    const emptyCell = counters.querySelector('[data-count="empty"]');

    if (emptyCell) {
        emptyCell.textContent = String(empty);
        emptyCell.closest('.attendance-counter').classList.toggle('is-done', empty === 0);
    }
}

function filterRows(form, term) {
    const needle = term.trim().toLowerCase();
    let visible = 0;

    form.querySelectorAll('[data-attendance-row]').forEach((row) => {
        const match = needle === '' || row.dataset.student.toLowerCase().includes(needle);
        row.hidden = !match;
        visible += match ? 1 : 0;
    });

    const empty = form.querySelector('[data-attendance-empty]');

    if (empty) {
        empty.hidden = visible > 0;
    }
}

export function initAttendanceForm(root = document) {
    const form = root.querySelector('[data-attendance-form]');

    if (!form) {
        return;
    }

    updateCounters(form);

    form.addEventListener('change', (event) => {
        if (event.target.matches('input[type="radio"]')) {
            updateCounters(form);
        }
    });

    // "Tandai semua hadir" (admin.js) sets the radios without firing change events.
    form.querySelector('[data-mark-all]')?.addEventListener('click', () => setTimeout(() => updateCounters(form)));

    form.querySelector('[data-attendance-filter]')?.addEventListener('input', (event) => filterRows(form, event.target.value));
}
