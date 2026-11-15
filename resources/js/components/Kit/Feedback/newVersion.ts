/*
 * After a deploy, the open page is the old build. Two documented hooks notice it, installed once by the shell's Toaster:
 * - Vite's `vite:preloadError` (a lazy chunk the deploy removed failed to load): the error is handled (preventDefault), then the page reloads, unless a form
 *   on it holds unsaved changes (a form frame with warn-on-leave on, formFrame.ts's pageUnsaved). Then the notice shows instead.
 * - Inertia's `location` event with `versionChange` (Inertia 3.6 and later; a background request keeps the page): the notice shows. Inertia's own handling is
 *   left as it is.
 * The notice is one quiet, persistent toast with a Reload action, shown once per page load. Pure: the Toaster passes the router, the toast and the reload.
 */
export const NEW_VERSION_MESSAGE = 'A new version is available.';

export type NewVersionHook = 'preload-error' | 'location';

/** What a hook does: a failed chunk on a page with nothing unsaved reloads; anything else shows the notice. */
export function newVersionStep(
    hook: NewVersionHook,
    unsaved: boolean,
): 'reload' | 'notice' {
    return hook === 'preload-error' && !unsaved ? 'reload' : 'notice';
}

export type NewVersionNotice = (
    message: string,
    options: {
        id: string;
        duration: number;
        action: { label: string; onClick: () => void };
    },
) => unknown;

export type NewVersionHooks = {
    /** `window`, where Vite dispatches `vite:preloadError`. */
    target: Pick<EventTarget, 'addEventListener'>;
    /** Inertia's `router.on('location', …)`. */
    onLocation: (
        listener: (event: { detail: { versionChange?: boolean } }) => void,
    ) => unknown;
    unsaved: () => boolean;
    reload: () => void;
    toast: NewVersionNotice;
};

let installed = false;

/** Listens to both hooks; a second call does nothing. */
export function installNewVersion(hooks: NewVersionHooks): void {
    if (installed) return;
    installed = true;
    let shown = false;
    const run = (hook: NewVersionHook) => {
        if (newVersionStep(hook, hooks.unsaved()) === 'reload')
            return hooks.reload();
        if (shown) return;
        shown = true;
        hooks.toast(NEW_VERSION_MESSAGE, {
            id: 'kit-new-version',
            duration: Infinity,
            action: { label: 'Reload', onClick: () => hooks.reload() },
        });
    };
    hooks.target.addEventListener('vite:preloadError', (event) => {
        event.preventDefault();
        run('preload-error');
    });
    hooks.onLocation((event) => {
        if (event.detail?.versionChange) run('location');
    });
}
