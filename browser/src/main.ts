import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import WorkbenchApp from './WorkbenchApp.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';
import DateTimeApp from './DateTimeApp.vue';
import PickersApp from './PickersApp.vue';

const query = new URLSearchParams(window.location.search);
createApp(query.has('pickers') ? PickersApp : query.has('datetime') ? DateTimeApp : query.has('workbench-menu') ? WorkbenchMenuApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
