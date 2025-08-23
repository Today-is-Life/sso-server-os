<div class="container max-w-6xl mx-auto p-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Neue Domain erstellen</h2>
                <button 
                    hx-get="/admin/domains"
                    hx-target="#main-content"
                    hx-push-url="true"
                    class="text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <form hx-post="/admin/domains"
              hx-target="#main-content"
              hx-swap="innerHTML"
              class="p-6 space-y-6">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Domain Name (Slug) *</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           placeholder="todayislife"
                           pattern="[a-z0-9-]+"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                    <p class="text-xs text-gray-500 mt-1">Nur Kleinbuchstaben, Zahlen und Bindestriche</p>
                </div>

                <div>
                    <label for="display_name" class="block text-sm font-medium text-gray-700 mb-2">Anzeigename *</label>
                    <input type="text" 
                           id="display_name" 
                           name="display_name" 
                           placeholder="Today Is Life"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>

                <div>
                    <label for="url" class="block text-sm font-medium text-gray-700 mb-2">Domain URL *</label>
                    <input type="url" 
                           id="url" 
                           name="url" 
                           placeholder="https://todayislife.test"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>

                <div>
                    <label for="logout_redirect_uri" class="block text-sm font-medium text-gray-700 mb-2">Logout Redirect URL *</label>
                    <input type="url" 
                           id="logout_redirect_uri" 
                           name="logout_redirect_uri" 
                           placeholder="https://todayislife.test"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
            </div>

            <div>
                <label for="allowed_origins" class="block text-sm font-medium text-gray-700 mb-2">Erlaubte Origins (eine pro Zeile)</label>
                <textarea id="allowed_origins" 
                          name="allowed_origins" 
                          rows="4"
                          placeholder="https://todayislife.test&#10;https://www.todayislife.test"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <div>
                <label for="redirect_uris" class="block text-sm font-medium text-gray-700 mb-2">Redirect URIs (eine pro Zeile)</label>
                <textarea id="redirect_uris" 
                          name="redirect_uris" 
                          rows="4"
                          placeholder="https://todayislife.test/auth/callback&#10;https://todayislife.test/oauth/callback"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <div class="flex justify-end space-x-4 pt-4 border-t">
                <button type="button"
                        hx-get="/admin/domains"
                        hx-target="#main-content"
                        hx-push-url="true"
                        class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-md">
                    Domain erstellen
                </button>
            </div>
        </form>
    </div>
</div>