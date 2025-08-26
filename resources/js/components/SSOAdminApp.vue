<template>
  <div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <h2>SSO Admin</h2>
      </div>
      
      <nav>
        <a 
          v-for="item in navItems" 
          :key="item.key"
          class="nav-item" 
          :class="{ active: currentView === item.key }"
          @click="navigate(item.key)"
          href="#"
        >
          {{ item.icon }} {{ item.label }}
        </a>
      </nav>
      
      <div class="user-info">
        <div class="user-card">
          <div class="user-name">{{ currentUser.name || 'Nicht angemeldet' }}</div>
          <div class="user-email">{{ currentUser.email || '' }}</div>
          <form action="/auth/logout" method="POST" class="logout-form">
            <input type="hidden" name="_token" :value="csrfToken">
            <button type="submit" class="btn btn-sm btn-secondary logout-btn">Logout</button>
          </form>
        </div>
      </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
      <component :is="currentComponent" />
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const currentView = ref('admin-dashboard')
const csrfToken = ref(window.csrfToken || '')
const currentUser = ref(window.currentUser || {})

const navItems = [
  { key: 'admin-dashboard', label: 'Dashboard', icon: '📊' },
  { key: 'user-manager', label: 'Benutzer', icon: '👥' },
  { key: 'group-manager', label: 'Gruppen', icon: '🏢' },
  { key: 'permission-manager', label: 'Berechtigungen', icon: '🔐' }
]

const currentComponent = computed(() => currentView.value)

const navigate = (view) => {
  currentView.value = view
  // Update URL without page reload
  const url = `/admin/${view.replace('-manager', '').replace('admin-', '')}`
  window.history.pushState({}, '', url)
}

onMounted(() => {
  // Handle browser back/forward
  window.addEventListener('popstate', () => {
    const path = window.location.pathname
    if (path.includes('/admin/users')) {
      currentView.value = 'user-manager'
    } else if (path.includes('/admin/groups')) {
      currentView.value = 'group-manager'
    } else if (path.includes('/admin/permissions')) {
      currentView.value = 'permission-manager'
    } else {
      currentView.value = 'admin-dashboard'
    }
  })
})
</script>

<style scoped>
.app-container {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 250px;
  background: white;
  border-right: 1px solid var(--border, #e2e8f0);
  padding: 1rem;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
  padding: 1rem 0;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--border, #e2e8f0);
}

.sidebar-header h2 {
  font-size: 1.5rem;
  color: var(--primary, #667eea);
}

.nav-item {
  display: block;
  padding: 0.75rem 1rem;
  margin: 0.25rem 0;
  color: var(--dark, #2d3748);
  text-decoration: none;
  border-radius: 0.5rem;
  transition: all 0.2s;
  cursor: pointer;
}

.nav-item:hover {
  background: var(--light, #f7fafc);
  color: var(--primary, #667eea);
}

.nav-item.active {
  background: var(--primary, #667eea);
  color: white;
}

.user-info {
  margin-top: auto;
  padding-top: 1rem;
}

.user-card {
  padding: 1rem;
  background: var(--light, #f7fafc);
  border-radius: 0.5rem;
}

.user-name {
  font-weight: 600;
}

.user-email {
  font-size: 0.75rem;
  color: #718096;
}

.logout-form {
  margin-top: 0.5rem;
}

.logout-btn {
  width: 100%;
}

.main-content {
  flex: 1;
  padding: 2rem;
  overflow-y: auto;
}
</style>