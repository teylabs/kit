// `@kit/ui` for the session-ui pages under test: only what they import from the kit barrel, so the build needs none of the shadcn pieces the whole
// barrel would pull in.
export { default as Alert } from '@/components/Kit/Feedback/Alert.vue';
export { default as Button } from '@/components/Kit/Form/Button.vue';
export { default as Field } from '@/components/Kit/Form/Field.vue';
export { default as FormStatus } from '@/components/Kit/Form/FormStatus.vue';
export { default as Input } from '@/components/Kit/Form/Input.vue';
export { default as ScriptForm } from '@/components/Kit/Form/ScriptForm.vue';
export { default as InputSelect } from '@/components/Kit/Form/InputSelect.vue';
export { default as InputCheckbox } from '@/components/Kit/Form/InputCheckbox.vue';
export { default as InputSwitch } from '@/components/Kit/Form/InputSwitch.vue';
export { default as Page } from '@/components/Kit/Layout/Page.vue';
export { default as Stack } from '@/components/Kit/Layout/Stack.vue';
export { default as PasswordInput } from '@/components/Kit/Form/PasswordInput.vue';
export { passkeySignInSupported, signInWithPasskey } from '@/components/Kit/Feedback/passkeyOffer';
export { default as AppearanceToggle } from '@/components/Kit/Appearance/AppearanceToggle.vue';
export { default as Toaster } from '@/components/Kit/Feedback/Toaster.vue';
// The notes composer's (notes.html).
export { default as BulkDrop } from '@/components/Kit/Form/BulkDrop.vue';
export { default as FormActions } from '@/components/Kit/Form/FormActions.vue';
export { default as Textarea } from '@/components/Kit/Form/Textarea.vue';
// The dashboard grid's (dashboard.html).
export { default as ActivityStream } from '@/components/Kit/Feed/ActivityStream.vue';
export { default as Avatar } from '@/components/Kit/Layout/Avatar.vue';
export { default as ScrollList } from '@/components/Kit/List/ScrollList.vue';
export { provideCard } from '@/components/Kit/Surface/surface';
export { default as PageGrid } from '@/components/Kit/Layout/PageGrid.vue';
export { default as PageAside } from '@/components/Kit/Layout/PageAside.vue';
export { default as Checklist } from '@/components/Kit/Feedback/Checklist.vue';
export type { ChecklistItem } from '@/components/Kit/Feedback/checklist';
// The Workspace shell's (shell.html): the Layout and its partials.
export { default as Breadcrumbs } from '@/components/Kit/Layout/Breadcrumbs.vue';
export { default as PasskeyOffer } from '@/components/Kit/Feedback/PasskeyOffer.vue';
export { default as Dialog } from '@/components/Kit/Overlay/Dialog.vue';
export { default as SearchBox } from '@/components/Kit/Search/SearchBox.vue';
export { opensSearch } from '@/components/Kit/Search/search';
export { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
export { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
// The workspace list's (workspaces.html).
export { default as Badge } from '@/components/Kit/Feedback/Badge.vue';
export { default as CopyButton } from '@/components/Kit/Action/CopyButton.vue';
export { checkedAtLabel, fromLine, fromPreview, fullRecordName, recordKind } from '@/components/Kit/Data/mailSending';
export { useTimezone } from '@/components/Kit/Data/useTimezone';
export { default as EmptyState } from '@/components/Kit/Feedback/EmptyState.vue';
export { default as PageSection } from '@/components/Kit/Layout/PageSection.vue';
export { default as Surface } from '@/components/Kit/Surface/Surface.vue';
// The settings forms' (forms.html).
export { default as FormCard } from '@/components/Kit/Form/FormCard.vue';
export { default as RadioCardGroup } from '@/components/Kit/Form/RadioCardGroup.vue';
export { default as TimezoneSelect } from '@/components/Kit/Form/TimezoneSelect.vue';
// The account and session forms' (forms.html): Account › Profile, Password, Security and Delete, the welcome steps and the notes wall.
export { default as DateTime } from '@/components/Kit/Data/DateTime.vue';
export { default as FileInput } from '@/components/Kit/Form/FileInput.vue';
export { default as Section } from '@/components/Kit/Form/Section.vue';
export { default as Dropdown } from '@/components/Kit/Menu/Dropdown.vue';
export { default as DropdownConfirm } from '@/components/Kit/Menu/DropdownConfirm.vue';
export { default as DropdownItem } from '@/components/Kit/Menu/DropdownItem.vue';
export { browserTimezone } from '@/components/Kit/timezone';
export { passkeyName, passkeysSupported, registerPasskey } from '@/components/Kit/Feedback/passkeyOffer';
// Settings › Security's (auth.html?auth=Workspace/Account/Security): Add a passkey with the password.
export { confirmPasskeyPassword } from '@/components/Kit/Feedback/passkeyOffer';
