import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import FormStatusApp from './FormStatusApp.vue';
import WorkbenchApp from './WorkbenchApp.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';
import StickyToastApp from './StickyToastApp.vue';

const query = new URLSearchParams(window.location.search);
createApp(query.has('sticky-toast') ? StickyToastApp : query.has('form-status') ? FormStatusApp : query.has('workbench-menu') ? WorkbenchMenuApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
