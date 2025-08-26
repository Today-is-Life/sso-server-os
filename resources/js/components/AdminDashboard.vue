<template>
  <div class="admin-dashboard">
    <h1 class="text-3xl font-bold mb-8">Dashboard</h1>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <StatsCard 
        v-for="stat in stats" 
        :key="stat.label"
        :value="stat.value"
        :label="stat.label"
        :loading="stat.loading"
        :icon="stat.icon"
      />
    </div>
    
    <!-- Recent Activities -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent Logins -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
          <h2 class="text-xl font-semibold">Letzte Anmeldungen</h2>
          <button 
            @click="loadRecentLogins"
            class="text-sm text-blue-600 hover:text-blue-800"
          >
            Aktualisieren
          </button>
        </div>
        <RecentLogins :logins="recentLogins" :loading="loadingLogins" />
      </div>
      
      <!-- Recent Activities -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold">Letzte Aktivitäten</h2>
        </div>
        <RecentActivities :activities="recentActivities" :loading="loadingActivities" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import StatsCard from './StatsCard.vue'
import RecentLogins from './RecentLogins.vue'
import RecentActivities from './RecentActivities.vue'

// Stats data
const stats = ref([
  { label: 'Benutzer', value: '-', loading: true, icon: 'users', endpoint: '/api/admin/stats/users' },
  { label: 'Gruppen', value: '-', loading: true, icon: 'folder', endpoint: '/api/admin/stats/groups' },
  { label: 'Domains', value: '-', loading: true, icon: 'globe', endpoint: '/api/admin/stats/domains' },
  { label: 'Aktive Sessions', value: '-', loading: true, icon: 'activity', endpoint: '/api/admin/stats/sessions' }
])

const recentLogins = ref([])
const recentActivities = ref([])
const loadingLogins = ref(true)
const loadingActivities = ref(true)

let refreshInterval = null

// Load stats
const loadStats = async () => {
  for (const stat of stats.value) {
    try {
      const response = await axios.get(stat.endpoint)
      stat.value = response.data
      stat.loading = false
    } catch (error) {
      console.error(`Error loading ${stat.label}:`, error)
      stat.value = 'Error'
      stat.loading = false
    }
  }
}

// Load recent logins
const loadRecentLogins = async () => {
  loadingLogins.value = true
  try {
    const response = await axios.get('/api/admin/recent-logins')
    recentLogins.value = response.data
  } catch (error) {
    console.error('Error loading recent logins:', error)
  } finally {
    loadingLogins.value = false
  }
}

// Load recent activities
const loadRecentActivities = async () => {
  loadingActivities.value = true
  try {
    const response = await axios.get('/api/admin/recent-activities')
    recentActivities.value = response.data
  } catch (error) {
    console.error('Error loading recent activities:', error)
  } finally {
    loadingActivities.value = false
  }
}

// Load all data
const loadAllData = () => {
  loadStats()
  loadRecentLogins()
  loadRecentActivities()
}

onMounted(() => {
  loadAllData()
  // Refresh every 30 seconds
  refreshInterval = setInterval(loadAllData, 30000)
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>