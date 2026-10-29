import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import WorkbenchApp from './WorkbenchApp.vue';

createApp(new URLSearchParams(window.location.search).has('workbench') ? WorkbenchApp : App).mount('#app');
