import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import WorkbenchApp from './WorkbenchApp.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';
import DateTimeApp from './DateTimeApp.vue';

const query = new URLSearchParams(window.location.search);
createApp(query.has('datetime') ? DateTimeApp : query.has('workbench-menu') ? WorkbenchMenuApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
