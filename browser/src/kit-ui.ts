// `@kit/ui` for the shell partials and session-ui pages under test: only what they import from the kit barrel, so the build needs none of the
// shadcn pieces the whole barrel would pull in.
export { default as Alert } from '@/components/Kit/Feedback/Alert.vue';
export { default as Button } from '@/components/Kit/Form/Button.vue';
export { default as Field } from '@/components/Kit/Form/Field.vue';
export { default as FormStatus } from '@/components/Kit/Form/FormStatus.vue';
export { default as Input } from '@/components/Kit/Form/Input.vue';
export { default as InputCheckbox } from '@/components/Kit/Form/InputCheckbox.vue';
export { default as Page } from '@/components/Kit/Layout/Page.vue';
export { default as PasswordInput } from '@/components/Kit/Form/PasswordInput.vue';
export { default as Toaster } from '@/components/Kit/Feedback/Toaster.vue';
