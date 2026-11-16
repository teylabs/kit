import { onBeforeUnmount, onMounted, readonly, ref, type Ref } from 'vue';
import { createFileDropGuard, type FileDropGuard } from './fileDropGuard';

const pageDragging = ref(false);
let guard: FileDropGuard | null = null;

/** Holds the page-wide file drop guard while the calling drop zone is mounted, and reports whether files are dragged over the page. */
export function useFileDropGuard(): { pageDragging: Readonly<Ref<boolean>> } {
    let release: (() => void) | null = null;
    onMounted(() => {
        guard ??= createFileDropGuard(window, (dragging) => {
            pageDragging.value = dragging;
        });
        release = guard.acquire();
    });
    onBeforeUnmount(() => {
        release?.();
        release = null;
    });
    return { pageDragging: readonly(pageDragging) };
}
