import { ref, watch, type Ref } from 'vue';
import { copyText } from './copy';

export type CopyState = 'idle' | 'copying' | 'copied' | 'failed';

/*
 * The copy state CopyButton, CopyField and TextCopy share: `copy()` writes the value through copyText and is `copied` only once the write resolved, `failed`
 * when it was refused or there was no clipboard. A press while one is in flight is ignored, and a changed value goes back to `idle`, so a write that resolves
 * after the value changed claims nothing about the new one.
 */
export function useCopy(value: () => string): {
    state: Ref<CopyState>;
    copy: () => Promise<void>;
} {
    const state = ref<CopyState>('idle');
    let revision = 0;
    watch(value, () => {
        revision++;
        state.value = 'idle';
    });
    async function copy(): Promise<void> {
        if (state.value === 'copying') return;
        const attempt = revision;
        state.value = 'copying';
        try {
            await copyText(value());
            if (attempt === revision) state.value = 'copied';
        } catch {
            if (attempt === revision) state.value = 'failed';
        }
    }

    return { state, copy };
}
