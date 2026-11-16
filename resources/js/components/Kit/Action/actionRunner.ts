import { router } from '@inertiajs/vue3';
import { getCurrentInstance, ref } from 'vue';
import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';
import { confirmationData } from '@/components/Kit/Overlay/confirmText';

/*
 * How the kit runs a server action descriptor: ActionBar, HeaderActions, and any page (ActionDialogs hosts its dialogs). A host that listens to `action` runs it
 * itself; otherwise an action with a `confirm` or a `form` asks first (the confirmation, typed when it has `typed` and sent as `confirmation` for the server to
 * re-check, with its `prompt` text; or the host's form), then an Inertia visit. A GET with neither visits at once. A refused visit keeps the dialog open with the
 * server's `errors`. Call it from setup; `emit` is the component's `action` emit, left out on a page that runs its own actions.
 */
export type ActionRunner = ReturnType<typeof useActionRunner>;

export function useActionRunner(
    emit?: (event: 'action', action: ActionDescriptor) => void,
) {
    const instance = getCurrentInstance();
    const hostRuns = () => Boolean(emit && instance?.vnode.props?.onAction);
    // Kept apart from `asking`: the dialog closes itself around the click that confirms it.
    const pending = ref<ActionDescriptor | null>(null);
    const asking = ref(false);
    // The server's refusal for the pending action (a prompt's `reason`, a form's fields), shown in its dialog.
    const errors = ref<Record<string, string>>({});
    // The action whose visit is in flight: its button spins, and every action waits until it finishes, so a second click never fires it twice.
    const running = ref<ActionDescriptor | null>(null);
    function visit(
        action: ActionDescriptor,
        confirmation?: string,
        data: Record<string, unknown> = {},
    ): void {
        if (running.value !== null) return;
        errors.value = {};
        router.visit(action.url, {
            method: action.method as
                'get' | 'post' | 'put' | 'patch' | 'delete',
            data: { ...confirmationData(confirmation), ...data },
            preserveScroll: true,
            onStart: () => {
                running.value = action;
            },
            onError: (refused: Record<string, string>) => {
                errors.value = refused;
                if (pending.value === action) asking.value = true;
            },
            onSuccess: () => {
                if (pending.value === action) {
                    asking.value = false;
                    pending.value = null;
                }
            },
            onFinish: () => {
                running.value = null;
            },
        });
    }
    const isRunning = (action: ActionDescriptor) =>
        running.value !== null &&
        (running.value === action ||
            (running.value.key ?? running.value.url) ===
                (action.key ?? action.url));
    function run(action: ActionDescriptor): void {
        if (action.disabled) return;
        if (hostRuns()) {
            emit!('action', action);
            return;
        }
        if (action.confirm || action.form) {
            errors.value = {};
            pending.value = action;
            // After a menu has closed and given focus back: opened in the same tick, the menu's own dismissal swallows the confirmation.
            setTimeout(() => (asking.value = true), 0);
            return;
        }
        visit(action);
    }

    return {
        hostRuns,
        pending,
        asking,
        errors,
        run,
        visit,
        running,
        isRunning,
    };
}
