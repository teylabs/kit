// `@kit/ui` for the session-ui pages under test: only what they import from the kit barrel, so the build needs none of the shadcn pieces the whole
// barrel would pull in.
export { default as Alert } from '@/components/Kit/Feedback/Alert.vue';
export { default as Button } from '@/components/Kit/Form/Button.vue';
export { default as Field } from '@/components/Kit/Form/Field.vue';
export { default as FormStatus } from '@/components/Kit/Form/FormStatus.vue';
export { default as Input } from '@/components/Kit/Form/Input.vue';
export { default as InputCheckbox } from '@/components/Kit/Form/InputCheckbox.vue';
export { default as Page } from '@/components/Kit/Layout/Page.vue';
export { default as PasswordInput } from '@/components/Kit/Form/PasswordInput.vue';
export { passkeySignInSupported, signInWithPasskey } from '@/components/Kit/Feedback/passkeyOffer';
export { default as AppearanceToggle } from '@/components/Kit/Appearance/AppearanceToggle.vue';
export { default as Toaster } from '@/components/Kit/Feedback/Toaster.vue';
// The notes composer's (notes.html).
export { default as BulkDrop } from '@/components/Kit/Form/BulkDrop.vue';
export { default as FormActions } from '@/components/Kit/Form/FormActions.vue';
export { default as InputSelect } from '@/components/Kit/Form/InputSelect.vue';
export { default as Textarea } from '@/components/Kit/Form/Textarea.vue';
// The dashboard grid's (dashboard.html).
export { default as ActivityStream } from '@/components/Kit/Feed/ActivityStream.vue';
export { default as Avatar } from '@/components/Kit/Layout/Avatar.vue';
export { default as ScrollList } from '@/components/Kit/List/ScrollList.vue';
