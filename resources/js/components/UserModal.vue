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
            {{ isEdit ? 'Benutzer bearbeiten' : 'Neuer Benutzer' }}
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

              <!-- Email -->
              <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                  E-Mail
                </label>
                <input
                  v-model="formData.email"
                  type="email"
                  id="email"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                >
              </div>

              <!-- Password (nur bei neuem Benutzer oder wenn geändert) -->
              <div v-if="!isEdit || changePassword">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                  Passwort {{ isEdit ? '(neu)' : '' }}
                </label>
                <input
                  v-model="formData.password"
                  type="password"
                  id="password"
                  :required="!isEdit"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                >
              </div>

              <!-- Password ändern Checkbox (nur bei Edit) -->
              <div v-if="isEdit" class="flex items-center">
                <input
                  v-model="changePassword"
                  type="checkbox"
                  id="changePassword"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                >
                <label for="changePassword" class="ml-2 block text-sm text-gray-900">
                  Passwort ändern
                </label>
              </div>

              <!-- Status -->
              <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                  Status
                </label>
                <select
                  v-model="formData.status"
                  id="status"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                >
                  <option value="active">Aktiv</option>
                  <option value="inactive">Inaktiv</option>
                  <option value="blocked">Gesperrt</option>
                </select>
              </div>

              <!-- Groups -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Gruppen
                </label>
                <div class="space-y-2 max-h-32 overflow-y-auto border border-gray-300 rounded-lg p-2">
                  <label 
                    v-for="group in availableGroups" 
                    :key="group.id"
                    class="flex items-center"
                  >
                    <input
                      type="checkbox"
                      :value="group.id"
                      v-model="formData.groups"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    >
                    <span class="ml-2 text-sm text-gray-700">{{ group.name }}</span>
                  </label>
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
  user: {
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
  email: '',
  password: '',
  status: 'active',
  groups: []
})

const changePassword = ref(false)
const availableGroups = ref([])

const handleSubmit = () => {
  const data = { ...formData }
  
  // Remove password if not changing it
  if (props.isEdit && !changePassword.value) {
    delete data.password
  }
  
  emit('save', data)
}

const loadGroups = async () => {
  try {
    const response = await axios.get('/api/admin/groups')
    availableGroups.value = response.data
  } catch (error) {
    console.error('Error loading groups:', error)
  }
}

onMounted(() => {
  loadGroups()
  
  if (props.user) {
    formData.name = props.user.name || ''
    formData.email = props.user.email || ''
    formData.status = props.user.status || 'active'
    formData.groups = props.user.groups?.map(g => g.id) || []
  }
})
</script>