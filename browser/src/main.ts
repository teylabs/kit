import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import FormAttrsApp from './FormAttrsApp.vue';
import FormStatusApp from './FormStatusApp.vue';
import SegmentedApp from './SegmentedApp.vue';
import WorkbenchApp from './WorkbenchApp.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';
import WorkbenchReorderApp from './WorkbenchReorderApp.vue';

const query = new URLSearchParams(window.location.search);
createApp(query.has('segmented') ? SegmentedApp : query.has('form-attrs') ? FormAttrsApp : query.has('form-status') ? FormStatusApp : query.has('workbench-menu') ? WorkbenchMenuApp : query.has('workbench-reorder') ? WorkbenchReorderApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
