<template>
  <div class="permission-manager">
    <div class="mb-6 flex justify-between items-center">
      <h1 class="text-3xl font-bold">Berechtigungsverwaltung</h1>
      <button 
        @click="showCreateModal = true"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
      >
        Neue Berechtigung
      </button>
    </div>

    <!-- Search -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Suche nach Name oder Code..."
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
        @input="filterPermissions"
      >
    </div>

    <!-- Permissions Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Name
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Code
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Beschreibung
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Gruppen
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Aktionen
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-if="loading">
            <td colspan="5" class="px-6 py-12 text-center">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
            </td>
          </tr>
          <tr v-else-if="filteredPermissions.length === 0">
            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
              Keine Berechtigungen gefunden
            </td>
          </tr>
          <tr v-else v-for="permission in filteredPermissions" :key="permission.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">{{ permission.name }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <code class="text-sm bg-gray-100 px-2 py-1 rounded">{{ permission.code }}</code>
            </td>
            <td class="px-6 py-4">
              <div class="text-sm text-gray-600">{{ permission.description || '-' }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span 
                v-for="group in permission.groups?.slice(0, 2)" 
                :key="group.id"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-1"
              >
                {{ group.name }}
              </span>
              <span 
                v-if="permission.groups?.length > 2"
                class="text-xs text-gray-500"
              >
                +{{ permission.groups.length - 2 }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              <button 
                @click="editPermission(permission)"
                class="text-blue-600 hover:text-blue-900 mr-3"
              >
                Bearbeiten
              </button>
              <button 
                @click="deletePermission(permission)"
                class="text-red-600 hover:text-red-900"
              >
                Löschen
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <PermissionModal 
      v-if="showCreateModal || showEditModal"
      :permission="selectedPermission"
      :isEdit="showEditModal"
      @save="savePermission"
      @close="closeModal"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import PermissionModal from './PermissionModal.vue'

const permissions = ref([])
const loading = ref(false)
const searchQuery = ref('')
const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedPermission = ref(null)

const filteredPermissions = computed(() => {
  if (!searchQuery.value) {
    return permissions.value
  }
  
  const query = searchQuery.value.toLowerCase()
  return permissions.value.filter(permission => 
    permission.name.toLowerCase().includes(query) ||
    permission.code.toLowerCase().includes(query) ||
    (permission.description && permission.description.toLowerCase().includes(query))
  )
})

const loadPermissions = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/admin/permissions')
    permissions.value = response.data
  } catch (error) {
    console.error('Error loading permissions:', error)
  } finally {
    loading.value = false
  }
}

const editPermission = (permission) => {
  selectedPermission.value = { ...permission }
  showEditModal.value = true
}

const deletePermission = async (permission) => {
  if (!confirm(`Möchten Sie die Berechtigung "${permission.name}" wirklich löschen?`)) {
    return
  }
  
  try {
    await axios.delete(`/api/admin/permissions/${permission.id}`)
    loadPermissions()
  } catch (error) {
    console.error('Error deleting permission:', error)
    alert('Fehler beim Löschen der Berechtigung')
  }
}

const savePermission = async (permissionData) => {
  try {
    if (showEditModal.value) {
      await axios.put(`/api/admin/permissions/${permissionData.id}`, permissionData)
    } else {
      await axios.post('/api/admin/permissions', permissionData)
    }
    closeModal()
    loadPermissions()
  } catch (error) {
    console.error('Error saving permission:', error)
    alert('Fehler beim Speichern der Berechtigung')
  }
}

const closeModal = () => {
  showCreateModal.value = false
  showEditModal.value = false
  selectedPermission.value = null
}

const filterPermissions = () => {
  // Computed property handles the filtering
}

onMounted(() => {
  loadPermissions()
})
</script>