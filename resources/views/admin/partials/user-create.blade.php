<div class="container max-w-6xl mx-auto p-6">
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Neuen Benutzer erstellen</h2>
                <button 
                    hx-get="/admin/users"
                    hx-target="#main-content"
                    hx-push-url="true"
                    class="text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <form hx-post="/admin/users"
              hx-target="#main-content"
              hx-swap="innerHTML"
              class="p-6 space-y-6">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">E-Mail *</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Passwort *</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required
                           minlength="8">
                    <p class="text-xs text-gray-500 mt-1">Mindestens 8 Zeichen</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Passwort bestätigen *</label>
                    <input type="password" 
                           id="password_confirmation" 
                           name="password_confirmation" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Gruppen zuweisen</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach(\App\Models\Group::where('is_active', true)->get() as $group)
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="group_{{ $group->id }}" 
                                   name="group_ids[]" 
                                   value="{{ $group->id }}"
                                   {{ $group->slug === 'user' ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="group_{{ $group->id }}" class="ml-2 text-sm text-gray-700">
                                {{ $group->name }}
                                @if($group->description)
                                    <span class="text-gray-500 text-xs block">{{ $group->description }}</span>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Zusätzliche Optionen</label>
                <div class="space-y-2">
                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="email_verified" 
                               name="email_verified" 
                               value="1"
                               checked
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="email_verified" class="ml-2 text-sm text-gray-700">
                            E-Mail als verifiziert markieren
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="send_welcome_email" 
                               name="send_welcome_email" 
                               value="1"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="send_welcome_email" class="ml-2 text-sm text-gray-700">
                            Willkommens-E-Mail senden
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-4 pt-4 border-t">
                <button type="button"
                        hx-get="/admin/users"
                        hx-target="#main-content"
                        hx-push-url="true"
                        class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-md">
                    Benutzer erstellen
                </button>
            </div>
        </form>
    </div>
</div>