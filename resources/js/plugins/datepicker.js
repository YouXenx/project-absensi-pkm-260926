import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import 'flatpickr/dist/flatpickr.css';

/**
 * Flatpickr for every <input type="date" data-datepicker>. The submitted value stays Y-m-d (what the controllers
 * validate); the visible field shows d/m/Y in Indonesian. min/max attributes are honoured.
 *
 * Date ranges: <input data-datepicker data-range-start="report"> + <input data-datepicker data-range-end="report">
 * keep "to" from being before "from".
 */

function rangePartner(input) {
    if (input.dataset.rangeStart) {
        return document.querySelector(`[data-range-end="${CSS.escape(input.dataset.rangeStart)}"]`);
    }

    if (input.dataset.rangeEnd) {
        return document.querySelector(`[data-range-start="${CSS.escape(input.dataset.rangeEnd)}"]`);
    }

    return null;
}

export function initDatepickers(root = document) {
    const inputs = [...root.querySelectorAll('input[data-datepicker]')].filter((input) => !input._flatpickr);

    inputs.forEach((input) => {
        flatpickr(input, {
            locale: Indonesian,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            altInputClass: `${input.className} datepicker-input`,
            allowInput: true,
            disableMobile: true,
            minDate: input.min || null,
            maxDate: input.max || null,
            onChange: (selectedDates, dateString, instance) => {
                const partner = rangePartner(instance.input)?._flatpickr;

                if (partner && instance.input.dataset.rangeStart) {
                    partner.set('minDate', dateString || null);
                } else if (partner) {
                    partner.set('maxDate', dateString || partner.input.max || null);
                }
            },
        });
    });

    // Apply the initial range limits once both ends exist.
    inputs.filter((input) => input.dataset.rangeStart).forEach((start) => {
        const end = rangePartner(start)?._flatpickr;

        if (end && start.value) {
            end.set('minDate', start.value);
        }

        if (end?.input.value) {
            start._flatpickr.set('maxDate', end.input.value);
        }
    });
}
