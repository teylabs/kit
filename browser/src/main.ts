import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import FormStatusApp from './FormStatusApp.vue';
import WorkbenchApp from './WorkbenchApp.vue';
import MoneyApp from './MoneyApp.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';
import WorkbenchReorderApp from './WorkbenchReorderApp.vue';

const query = new URLSearchParams(window.location.search);
createApp(query.has('money') ? MoneyApp : query.has('form-status') ? FormStatusApp : query.has('workbench-menu') ? WorkbenchMenuApp : query.has('workbench-reorder') ? WorkbenchReorderApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
