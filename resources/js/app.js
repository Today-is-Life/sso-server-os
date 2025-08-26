import './bootstrap';
import { createApp } from 'vue';

// Import Components
import AdminDashboard from './components/AdminDashboard.vue';
import UserManager from './components/UserManager.vue';
import GroupManager from './components/GroupManager.vue';
import PermissionManager from './components/PermissionManager.vue';
import SSOAdminApp from './components/SSOAdminApp.vue';

// Create Vue App for Admin section
const app = createApp({
    data() {
        return {
            currentComponent: window.currentView || 'admin-dashboard'
        }
    },
    template: `
        <component :is="currentComponent"></component>
    `
});

// Register Components
app.component('admin-dashboard', AdminDashboard);
app.component('user-manager', UserManager);
app.component('group-manager', GroupManager);
app.component('permission-manager', PermissionManager);
app.component('sso-admin-app', SSOAdminApp);

// Mount app if element exists
const mountElement = document.querySelector('#vue-app');
if (mountElement) {
    app.mount('#vue-app');
}
