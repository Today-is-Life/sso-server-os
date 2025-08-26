<template>
  <div class="group-manager">
    <div class="mb-6 flex justify-between items-center">
      <h1 class="text-3xl font-bold">Gruppenverwaltung</h1>
      <button 
        @click="showCreateModal = true"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
      >
        Neue Gruppe
      </button>
    </div>

    <!-- Groups Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-if="loading" class="col-span-full text-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
      </div>

      <div v-else-if="groups.length === 0" class="col-span-full text-center py-12">
        <p class="text-gray-500">Keine Gruppen vorhanden</p>
      </div>

      <div 
        v-else
        v-for="group in groups" 
        :key="group.id"
        class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow"
      >
        <div class="p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">{{ group.name }}</h3>
            <div class="flex space-x-2">
              <button 
                @click="editGroup(group)"
                class="text-blue-600 hover:text-blue-800"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
              <button 
                @click="deleteGroup(group)"
                class="text-red-600 hover:text-red-800"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
              </button>
            </div>
          </div>

          <p class="text-gray-600 text-sm mb-4">{{ group.description || 'Keine Beschreibung' }}</p>

          <div class="space-y-2">
            <div class="flex items-center text-sm text-gray-500">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
              </svg>
              <span>{{ group.users_count || 0 }} Benutzer</span>
            </div>
            <div class="flex items-center text-sm text-gray-500">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span>{{ group.permissions_count || 0 }} Berechtigungen</span>
            </div>
          </div>

          <!-- Permissions Preview -->
          <div v-if="group.permissions && group.permissions.length > 0" class="mt-4">
            <p class="text-xs font-medium text-gray-700 mb-2">Berechtigungen:</p>
            <div class="flex flex-wrap gap-1">
              <span 
                v-for="(perm, idx) in group.permissions.slice(0, 3)" 
                :key="idx"
                class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded"
              >
                {{ perm.name }}
              </span>
              <span 
                v-if="group.permissions.length > 3"
                class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded"
              >
                +{{ group.permissions.length - 3 }} weitere
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <GroupModal 
      v-if="showCreateModal || showEditModal"
      :group="selectedGroup"
      :isEdit="showEditModal"
      @save="saveGroup"
      @close="closeModal"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import GroupModal from './GroupModal.vue'

const groups = ref([])
const loading = ref(false)
const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedGroup = ref(null)

const loadGroups = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/admin/groups')
    groups.value = response.data
  } catch (error) {
    console.error('Error loading groups:', error)
  } finally {
    loading.value = false
  }
}

const editGroup = (group) => {
  selectedGroup.value = { ...group }
  showEditModal.value = true
}

const deleteGroup = async (group) => {
  if (!confirm(`Möchten Sie die Gruppe "${group.name}" wirklich löschen?`)) {
    return
  }
  
  try {
    await axios.delete(`/api/admin/groups/${group.id}`)
    loadGroups()
  } catch (error) {
    console.error('Error deleting group:', error)
    alert('Fehler beim Löschen der Gruppe')
  }
}

const saveGroup = async (groupData) => {
  try {
    if (showEditModal.value) {
      await axios.put(`/api/admin/groups/${groupData.id}`, groupData)
    } else {
      await axios.post('/api/admin/groups', groupData)
    }
    closeModal()
    loadGroups()
  } catch (error) {
    console.error('Error saving group:', error)
    alert('Fehler beim Speichern der Gruppe')
  }
}

const closeModal = () => {
  showCreateModal.value = false
  showEditModal.value = false
  selectedGroup.value = null
}

onMounted(() => {
  loadGroups()
})
</script>