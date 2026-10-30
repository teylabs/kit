import { createApp } from 'vue';
import './app.css';
import { initializeTheme } from '@/components/Kit/Appearance/useAppearance';
import App from './App.vue';
import AppearanceApp from './AppearanceApp.vue';
import AuthApp from './AuthApp.vue';
import FormAttrsApp from './FormAttrsApp.vue';
import FormSavedApp from './FormSavedApp.vue';
import FormStatusApp from './FormStatusApp.vue';
import WorkbenchApp from './WorkbenchApp.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';
import StickyToastApp from './StickyToastApp.vue';
import ShellMarkApp from './ShellMarkApp.vue';
import WorkbenchReorderApp from './WorkbenchReorderApp.vue';

const query = new URLSearchParams(window.location.search);
if (query.has('appearance')) initializeTheme();
createApp(query.has('auth') ? AuthApp : query.has('appearance') ? AppearanceApp : query.has('shell-mark') ? ShellMarkApp : query.has('form-attrs') ? FormAttrsApp : query.has('form-saved') ? FormSavedApp : query.has('sticky-toast') ? StickyToastApp : query.has('form-status') ? FormStatusApp : query.has('workbench-menu') ? WorkbenchMenuApp : query.has('workbench-reorder') ? WorkbenchReorderApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
