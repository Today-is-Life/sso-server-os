import './bootstrap';
import { createApp } from 'vue';

// Import Components
import AdminDashboard from './components/AdminDashboard.vue';
import UserManager from './components/UserManager.vue';
import GroupManager from './components/GroupManager.vue';
import PermissionManager from './components/PermissionManager.vue';

// Create Vue App for Admin section
const app = createApp({});

// Register Components
app.component('admin-dashboard', AdminDashboard);
app.component('user-manager', UserManager);
app.component('group-manager', GroupManager);
app.component('permission-manager', PermissionManager);

// Mount app if element exists
const mountElement = document.querySelector('#vue-app');
if (mountElement) {
    // Set current component based on window.currentView
    app.config.globalProperties.currentComponent = window.currentView || 'admin-dashboard';
    
    app.mount('#vue-app');
}
