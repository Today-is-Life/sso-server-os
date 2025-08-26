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
            {{ isEdit ? 'Berechtigung bearbeiten' : 'Neue Berechtigung' }}
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
                  placeholder="z.B. Benutzer verwalten"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                >
              </div>

              <!-- Code -->
              <div>
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
                  Code
                </label>
                <input
                  v-model="formData.code"
                  type="text"
                  id="code"
                  required
                  placeholder="z.B. users.manage"
                  pattern="[a-z0-9._-]+"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 font-mono"
                >
                <p class="mt-1 text-xs text-gray-500">
                  Nur Kleinbuchstaben, Zahlen, Punkte, Unterstriche und Bindestriche erlaubt
                </p>
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
                  placeholder="Optionale Beschreibung der Berechtigung"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                ></textarea>
              </div>

              <!-- Category -->
              <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                  Kategorie
                </label>
                <select
                  v-model="formData.category"
                  id="category"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                >
                  <option value="">Keine Kategorie</option>
                  <option value="users">Benutzer</option>
                  <option value="groups">Gruppen</option>
                  <option value="permissions">Berechtigungen</option>
                  <option value="system">System</option>
                  <option value="content">Inhalt</option>
                </select>
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
import { onMounted, reactive } from 'vue'

const props = defineProps({
  permission: {
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
  code: '',
  description: '',
  category: ''
})

const handleSubmit = () => {
  // Validate code format
  if (!/^[a-z0-9._-]+$/.test(formData.code)) {
    alert('Der Code enthält ungültige Zeichen. Nur Kleinbuchstaben, Zahlen, Punkte, Unterstriche und Bindestriche sind erlaubt.')
    return
  }
  
  emit('save', { ...formData })
}

onMounted(() => {
  if (props.permission) {
    formData.name = props.permission.name || ''
    formData.code = props.permission.code || ''
    formData.description = props.permission.description || ''
    formData.category = props.permission.category || ''
  }
})
</script>