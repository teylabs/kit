import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';

createApp(new URLSearchParams(window.location.search).has('workbench-menu') ? WorkbenchMenuApp : App).mount('#app');
