// The page-wide file drop guard (kit), from AB's dropzone.
/**
 * The page-wide file drop guard. While any drop zone is mounted, a file dragged onto the page but released outside a zone
 * (a near-miss, or a zone that leaves its drop to the host) must never fall through to the browser, which would open the file
 * and leave the app. Zones acquire the guard on mount and release it on unmount; the listeners live while at least one holds it.
 * `dragging` reports whether files are being dragged over the page, so zones can show their active state as soon as a drag begins.
 */
export type FileDropGuard = {
    acquire: () => () => void;
    holders: () => number;
    dragging: () => boolean;
};

type GuardTarget = Pick<
    EventTarget,
    'addEventListener' | 'removeEventListener'
>;

const carriesFiles = (event: DragEvent): boolean =>
    Boolean(
        event.dataTransfer?.types &&
        Array.from(event.dataTransfer.types).includes('Files'),
    );

export function createFileDropGuard(
    target: GuardTarget,
    onDragging: (dragging: boolean) => void = () => {},
): FileDropGuard {
    let count = 0;
    let depth = 0;
    const set = (next: number) => {
        const was = depth > 0;
        depth = Math.max(0, next);

        if (was !== depth > 0) {
            onDragging(depth > 0);
        }
    };
    const enter = (event: Event) => {
        if (carriesFiles(event as DragEvent)) {
            set(depth + 1);
        }
    };
    const leave = (event: Event) => {
        if (carriesFiles(event as DragEvent)) {
            set(depth - 1);
        }
    };
    const over = (event: Event) => {
        const drag = event as DragEvent;

        if (!carriesFiles(drag)) {
            return;
        }

        // A zone already accepted this drag; anywhere else shows the no-drop cursor.
        if (!drag.defaultPrevented && drag.dataTransfer) {
            drag.dataTransfer.dropEffect = 'none';
        }

        drag.preventDefault();
    };
    // Prevention runs as the drop bubbles, so a zone sees it first; the drag ends on capture, so a zone that stops the drop still ends it.
    const drop = (event: Event) => {
        if (carriesFiles(event as DragEvent)) {
            event.preventDefault();
        }
    };
    const end = () => set(0);
    const listeners: [string, (event: Event) => void, boolean][] = [
        ['dragenter', enter, true],
        ['dragleave', leave, true],
        ['dragover', over, false],
        ['drop', drop, false],
        ['drop', end, true],
        ['dragend', end, true],
    ];

    return {
        acquire() {
            if (count++ === 0) {
                listeners.forEach(([type, listener, capture]) =>
                    target.addEventListener(type, listener, capture),
                );
            }

            let released = false;

            return () => {
                if (released) {
                    return;
                }

                released = true;

                if (--count === 0) {
                    listeners.forEach(([type, listener, capture]) =>
                        target.removeEventListener(type, listener, capture),
                    );
                    set(0);
                }
            };
        },
        holders: () => count,
        dragging: () => depth > 0,
    };
}
