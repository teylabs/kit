import { Form } from '@inertiajs/vue3';

/**
 * Makes `setDefaultsOnSuccess` true by default on every Inertia `<Form>`: after a successful submit the submitted values become the
 * defaults, so a saved form is no longer dirty and the leave-page prompt stops (useForm already behaves this way). Call it once in the
 * app's entry, before mounting. A form can still opt out with `:set-defaults-on-success="false"`. Returns whether the default was set
 * (false when this Inertia version has no such prop).
 */
export function applyFormDefaults(): boolean {
    const props = (
        Form as unknown as { props?: Record<string, { default?: unknown }> }
    ).props;

    if (!props?.setDefaultsOnSuccess) {
        return false;
    }

    props.setDefaultsOnSuccess.default = true;

    return true;
}
