import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import Welcome from './Welcome.vue'
const router = createRouter({ history: createWebHistory(), routes: [{ path: '/', component: Welcome }] })
createApp(App).use(router).mount('#app')
