import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import MenuApp from './MenuApp.vue';
import WorkbenchApp from './WorkbenchApp.vue';

const query = new URLSearchParams(window.location.search);
createApp(query.has('menu') ? MenuApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
