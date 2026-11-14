import { ref, watch, type Ref } from 'vue';
import { copyText } from './copy';

export type CopyState = 'idle' | 'copying' | 'copied' | 'failed';

/*
 * The copy state CopyButton, CopyField and TextCopy share: `copy()` writes the value through copyText and is `copied` only once the write resolved, `failed`
 * when it was refused or there was no clipboard. One write at a time: a press while one is in flight is ignored, also after the value changed, so writes
 * never overlap or land out of order. A changed value goes back to `idle`, once no write is in flight, and a write that settles after the value changed
 * claims nothing about the new one: it ends in `idle`.
 */
export function useCopy(value: () => string): {
    state: Ref<CopyState>;
    copy: () => Promise<void>;
} {
    const state = ref<CopyState>('idle');
    let revision = 0;
    watch(value, () => {
        revision++;
        // A write still in flight keeps `copying` (and the guard below) until it settles.
        if (state.value !== 'copying') state.value = 'idle';
    });
    async function copy(): Promise<void> {
        if (state.value === 'copying') return;
        const attempt = revision;
        state.value = 'copying';
        try {
            await copyText(value());
            state.value = attempt === revision ? 'copied' : 'idle';
        } catch {
            state.value = attempt === revision ? 'failed' : 'idle';
        }
    }

    return { state, copy };
}
