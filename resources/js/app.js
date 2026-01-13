import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

import AppShell from './components/AppShell.vue';
import Dashboard from './pages/Dashboard.vue';
import AhpSetup from './pages/AhpSetup.vue';

const routes = [
  { path: '/', component: Dashboard },
  { path: '/ahp', component: AhpSetup },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

createApp(AppShell).use(router).mount('#app');
