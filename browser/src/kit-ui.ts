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
// The shell's Layout and partials (?shell-width).
export { default as AppearanceToggle } from '@/components/Kit/Appearance/AppearanceToggle.vue';
export { default as Avatar } from '@/components/Kit/Layout/Avatar.vue';
export { default as Dialog } from '@/components/Kit/Overlay/Dialog.vue';
export { default as PasskeyOffer } from '@/components/Kit/Feedback/PasskeyOffer.vue';
export { default as SettingsFrame } from '@/components/Kit/Layout/SettingsFrame.vue';
export { default as SearchBox } from '@/components/Kit/Search/SearchBox.vue';
export { opensSearch } from '@/components/Kit/Search/search';
export { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger, Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/dropdown-menu';
