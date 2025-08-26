<template>
  <div class="px-6 py-4">
    <div v-if="loading" class="animate-pulse">
      <div v-for="i in 5" :key="i" class="flex items-center justify-between py-3 border-b border-gray-100">
        <div class="flex-1">
          <div class="h-4 w-32 bg-gray-200 rounded mb-2"></div>
          <div class="h-3 w-24 bg-gray-100 rounded"></div>
        </div>
        <div class="h-3 w-20 bg-gray-100 rounded"></div>
      </div>
    </div>
    
    <div v-else-if="logins.length === 0" class="text-gray-500 text-center py-8">
      Keine kürzlichen Anmeldungen
    </div>
    
    <div v-else>
      <div 
        v-for="login in logins" 
        :key="login.id"
        class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0"
      >
        <div class="flex-1">
          <div class="font-medium text-gray-900">{{ login.user_name }}</div>
          <div class="text-sm text-gray-500">{{ login.email }}</div>
        </div>
        <div class="text-sm text-gray-500">
          {{ formatTime(login.created_at) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  logins: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const formatTime = (timestamp) => {
  const date = new Date(timestamp)
  const now = new Date()
  const diff = Math.floor((now - date) / 1000) // Difference in seconds
  
  if (diff < 60) return 'Gerade eben'
  if (diff < 3600) return `Vor ${Math.floor(diff / 60)} Minuten`
  if (diff < 86400) return `Vor ${Math.floor(diff / 3600)} Stunden`
  
  return date.toLocaleDateString('de-DE', { 
    day: '2-digit', 
    month: '2-digit', 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}
</script>