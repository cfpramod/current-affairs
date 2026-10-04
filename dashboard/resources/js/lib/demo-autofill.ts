// Live-demo helper: typing "." into an empty decision field types a prepared
// text, so the presenter doesn't spend stage time typing. Enabled only when
// the server shares `demoAutofill` (SMARTFLOW_DEMO_AUTOFILL=true).
import type { Directive } from 'vue';
import type { ActionKey } from '@/types/console';

export type DemoField = 'submit' | 'accept' | 'deny' | 'next';

type BatchScript = {
    submit?: Partial<Record<ActionKey, string>>;
    accept?: string;
    deny?: string;
    next?: string;
};

const fallback: Required<BatchScript> = {
    submit: {
        RUN_AS_PLANNED:
            'Material and temperature evidence support charging as planned.',
        BUFFER: 'Released safety stock covers the full charge; charging from it as planned.',
        EXPEDITE:
            'Incoming material is late for the charge; asking logistics for a faster slot.',
        REROUTE:
            'Barge route is unreliable; asking logistics to move the last leg to road.',
        QUARANTINE:
            'Temperature evidence needs QA review before this lot is used.',
    },
    accept: 'Evidence reviewed and the request is justified.',
    deny: 'Evidence does not support this request; returning it to the operator.',
    next: 'Confirm with the operator and record the outcome in the ERP.',
};

const scripts: Record<string, BatchScript> = {
    'operations-demo-v1-batch-002': {
        submit: {
            QUARANTINE:
                'Lot spent 148 of its 120 allowed minutes above 8 °C on the hot afternoon; hold it for QA.',
        },
        accept: 'Excursion confirmed in the readings; the lot cannot be used until assessed.',
        deny: 'Readings point to a sensor fault, not a real excursion; returning to the operator.',
        next: 'Block lot 002 in the ERP and sample it for stability testing.',
    },
};

/** The prepared text for a batch, field and (for submissions) action. */
export function demoText(
    batchId: string,
    field: DemoField,
    action?: ActionKey,
): string {
    const script = scripts[batchId] ?? {};

    if (field === 'submit') {
        const key = action ?? 'RUN_AS_PLANNED';

        return script.submit?.[key] ?? fallback.submit[key] ?? '';
    }

    return script[field] ?? fallback[field];
}

type FillElement = (HTMLInputElement | HTMLTextAreaElement) & {
    demoText?: string | null;
    demoTyping?: boolean;
};

const TYPE_DELAY_MS = 14;

function typeInto(el: FillElement, text: string): void {
    el.demoTyping = true;
    let index = 0;

    const step = () => {
        index += 1;
        el.value = text.slice(0, index);
        el.dispatchEvent(new Event('input', { bubbles: true }));

        if (index < text.length) {
            window.setTimeout(step, TYPE_DELAY_MS);
        } else {
            el.demoTyping = false;
        }
    };

    step();
}

/**
 * v-demo-fill="text": "." in an empty field types `text`; null disables it.
 */
export const vDemoFill: Directive<FillElement, string | null> = {
    mounted(el, binding) {
        el.demoText = binding.value;
        el.addEventListener('keydown', (event: Event) => {
            if (
                !(event instanceof KeyboardEvent) ||
                (event.key !== '.' && event.code !== 'Period') ||
                el.value !== '' ||
                !el.demoText ||
                el.demoTyping
            ) {
                return;
            }

            event.preventDefault();
            typeInto(el, el.demoText);
        });
    },
    updated(el, binding) {
        el.demoText = binding.value;
    },
};
