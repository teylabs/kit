import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import FormAttrsApp from './FormAttrsApp.vue';
import FormStatusApp from './FormStatusApp.vue';
import TagsApp from './TagsApp.vue';
import WorkbenchApp from './WorkbenchApp.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';
import StickyToastApp from './StickyToastApp.vue';
import WorkbenchReorderApp from './WorkbenchReorderApp.vue';

const query = new URLSearchParams(window.location.search);
createApp(query.has('sticky-toast') ? StickyToastApp : query.has('form-attrs') ? FormAttrsApp : query.has('form-status') ? FormStatusApp : query.has('tags') ? TagsApp : query.has('workbench-menu') ? WorkbenchMenuApp : query.has('workbench-reorder') ? WorkbenchReorderApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
