<template>
  <div class="px-6 py-4">
    <div v-if="loading" class="animate-pulse">
      <div v-for="i in 5" :key="i" class="py-3 border-b border-gray-100">
        <div class="h-4 w-48 bg-gray-200 rounded mb-2"></div>
        <div class="h-3 w-32 bg-gray-100 rounded"></div>
      </div>
    </div>
    
    <div v-else-if="activities.length === 0" class="text-gray-500 text-center py-8">
      Keine kürzlichen Aktivitäten
    </div>
    
    <div v-else>
      <div 
        v-for="activity in activities" 
        :key="activity.id"
        class="py-3 border-b border-gray-100 last:border-0"
      >
        <div class="flex items-start">
          <div :class="getActivityIcon(activity.type)" class="mt-1 mr-3">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path v-if="activity.type === 'create'" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
              <path v-else-if="activity.type === 'update'" fill-rule="evenodd" d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" clip-rule="evenodd"></path>
              <path v-else-if="activity.type === 'delete'" fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
          </div>
          <div class="flex-1">
            <div class="text-sm text-gray-900">{{ activity.description }}</div>
            <div class="text-xs text-gray-500 mt-1">
              {{ activity.user }} - {{ formatTime(activity.created_at) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  activities: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const getActivityIcon = (type) => {
  const iconClasses = {
    create: 'text-green-500',
    update: 'text-blue-500',
    delete: 'text-red-500',
    login: 'text-gray-500'
  }
  return iconClasses[type] || 'text-gray-500'
}

const formatTime = (timestamp) => {
  const date = new Date(timestamp)
  const now = new Date()
  const diff = Math.floor((now - date) / 1000)
  
  if (diff < 60) return 'Gerade eben'
  if (diff < 3600) return `Vor ${Math.floor(diff / 60)} Min.`
  if (diff < 86400) return `Vor ${Math.floor(diff / 3600)} Std.`
  
  return date.toLocaleDateString('de-DE', { 
    day: '2-digit', 
    month: '2-digit' 
  })
}
</script>