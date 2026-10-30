// `@kit/ui` for the shell partials under test: only what they import from the kit barrel (FocusShell's Toaster), so the build needs none of the
// shadcn pieces the whole barrel would pull in.
export { default as Toaster } from '@/components/Kit/Feedback/Toaster.vue';
