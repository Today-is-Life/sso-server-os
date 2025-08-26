<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div 
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$emit('close')"
      ></div>

      <!-- Modal -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
            {{ isEdit ? 'Gruppe bearbeiten' : 'Neue Gruppe' }}
          </h3>

          <form @submit.prevent="handleSubmit">
            <div class="space-y-4">
              <!-- Name -->
              <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                  Name
                </label>
                <input
                  v-model="formData.name"
                  type="text"
                  id="name"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                >
              </div>

              <!-- Description -->
              <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                  Beschreibung
                </label>
                <textarea
                  v-model="formData.description"
                  id="description"
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                ></textarea>
              </div>

              <!-- Permissions -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Berechtigungen
                </label>
                <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-3">
                  <div v-if="loadingPermissions" class="text-center py-4">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                  </div>
                  <div v-else class="space-y-1">
                    <label 
                      v-for="permission in availablePermissions" 
                      :key="permission.id"
                      class="flex items-center p-1 hover:bg-gray-50 rounded"
                    >
                      <input
                        type="checkbox"
                        :value="permission.id"
                        v-model="formData.permissions"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      >
                      <span class="ml-2 text-sm text-gray-700">
                        {{ permission.name }}
                        <span class="text-xs text-gray-500 ml-1">({{ permission.code }})</span>
                      </span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>

        <!-- Buttons -->
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            @click="handleSubmit"
            type="button"
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
          >
            {{ isEdit ? 'Aktualisieren' : 'Erstellen' }}
          </button>
          <button
            @click="$emit('close')"
            type="button"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Abbrechen
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import axios from 'axios'

const props = defineProps({
  group: {
    type: Object,
    default: null
  },
  isEdit: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['save', 'close'])

const formData = reactive({
  name: '',
  description: '',
  permissions: []
})

const availablePermissions = ref([])
const loadingPermissions = ref(false)

const handleSubmit = () => {
  emit('save', { ...formData })
}

const loadPermissions = async () => {
  loadingPermissions.value = true
  try {
    const response = await axios.get('/api/admin/permissions')
    availablePermissions.value = response.data
  } catch (error) {
    console.error('Error loading permissions:', error)
  } finally {
    loadingPermissions.value = false
  }
}

onMounted(() => {
  loadPermissions()
  
  if (props.group) {
    formData.name = props.group.name || ''
    formData.description = props.group.description || ''
    formData.permissions = props.group.permissions?.map(p => p.id) || []
  }
})
</script>