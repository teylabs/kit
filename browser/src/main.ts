import { createApp } from 'vue';
import './app.css';
import App from './App.vue';
import TagsApp from './TagsApp.vue';
import WorkbenchApp from './WorkbenchApp.vue';
import WorkbenchMenuApp from './WorkbenchMenuApp.vue';

const query = new URLSearchParams(window.location.search);
createApp(query.has('tags') ? TagsApp : query.has('workbench-menu') ? WorkbenchMenuApp : query.has('workbench') ? WorkbenchApp : App).mount('#app');
