<div>
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Berechtigungen verwalten</h1>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <select id="domain-select" class="form-control" style="width: 200px;" onchange="toggleSyncButton()">
                    <option value="">-- Domain wählen --</option>
                    @foreach(\App\Models\Domain::where('is_active', true)->get() as $domain)
                        <option value="{{ $domain->id }}">{{ $domain->display_name }}</option>
                    @endforeach
                </select>
                <button id="sync-button" onclick="syncDomainPermissions()" class="btn" disabled style="opacity: 0.5; cursor: not-allowed;">
                    🔄 Permissions synchronisieren
                </button>
                <div id="sync-status" style="display: none; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem;"></div>
            </div>
        </div>
        
        <!-- Permissions by Category -->
        @php
            $groupedPermissions = $permissions->groupBy('category');
        @endphp
        
        @foreach($groupedPermissions as $category => $categoryPermissions)
        <div class="card">
            <div class="card-header">
                <h3 style="text-transform: capitalize;">{{ $category }}</h3>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Beschreibung</th>
                        <th>Module</th>
                        <th>System</th>
                        <th>Zugewiesene Gruppen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categoryPermissions as $permission)
                    <tr>
                        <td>{{ $permission->name }}</td>
                        <td><code>{{ $permission->slug }}</code></td>
                        <td>{{ $permission->description }}</td>
                        <td>{{ $permission->module }}</td>
                        <td>
                            @if($permission->is_system)
                                <span class="badge badge-danger">System</span>
                            @else
                                <span class="badge">Normal</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $assignedGroups = $groups->filter(function($group) use ($permission) {
                                    return $group->permissions->contains('id', $permission->id);
                                });
                            @endphp
                            @foreach($assignedGroups as $group)
                                <span class="badge" style="background: {{ $group->color }}; color: white;">
                                    {{ $group->name }}
                                </span>
                            @endforeach
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach
        
        <!-- Assign Permissions to Groups -->
        <div class="card">
            <div class="card-header">
                <h3>Berechtigungen zuweisen</h3>
            </div>
            <div style="padding: 1rem;">
                <form hx-post="/admin/permissions/assign" hx-target="#main-content">
                    <div class="form-group">
                        <label class="form-label">Gruppe</label>
                        <select name="group_id" class="form-control" required onchange="loadGroupPermissions(this.value)">
                            <option value="">-- Gruppe wählen --</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" data-permissions="{{ json_encode($group->permissions->pluck('id')) }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Berechtigungen</label>
                        <div id="permissions-list" style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border); padding: 1rem; border-radius: 0.375rem;">
                            @foreach($permissions as $permission)
                            <label style="display: block; margin-bottom: 0.5rem;">
                                <input type="checkbox" 
                                       name="permission_ids[]" 
                                       value="{{ $permission->id }}"
                                       data-permission-id="{{ $permission->id }}">
                                {{ $permission->name }} (<code>{{ $permission->slug }}</code>)
                            </label>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn">Berechtigungen aktualisieren</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSyncButton() {
    const domainSelect = document.getElementById('domain-select');
    const syncButton = document.getElementById('sync-button');
    
    if (domainSelect.value) {
        syncButton.disabled = false;
        syncButton.style.opacity = '1';
        syncButton.style.cursor = 'pointer';
    } else {
        syncButton.disabled = true;
        syncButton.style.opacity = '0.5';
        syncButton.style.cursor = 'not-allowed';
    }
}

function showSyncStatus(message, isError = false) {
    const statusDiv = document.getElementById('sync-status');
    statusDiv.style.display = 'block';
    statusDiv.textContent = message;
    statusDiv.style.background = isError ? '#fed7d7' : '#c6f6d5';
    statusDiv.style.color = isError ? '#742a2a' : '#22543d';
    statusDiv.style.border = isError ? '1px solid #fc8181' : '1px solid #9ae6b4';
    
    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}

function syncDomainPermissions() {
    const domainSelect = document.getElementById('domain-select');
    const domainId = domainSelect.value;
    const button = document.getElementById('sync-button');
    
    if (!domainId) return;
    
    // Show loading
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Synchronisiere...';
    button.disabled = true;
    
    fetch('/admin/permissions/sync', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ domain_id: domainId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSyncStatus(data.message || 'Erfolgreich synchronisiert');
            // Reload permissions list
            htmx.ajax('GET', '/admin/permissions', {
                target: '#main-content',
                swap: 'innerHTML'
            });
        } else {
            showSyncStatus(data.error || 'Synchronisierung fehlgeschlagen', true);
        }
    })
    .catch(error => {
        showSyncStatus('Netzwerkfehler: ' + error.message, true);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function loadGroupPermissions(groupId) {
    // Reset all checkboxes
    const checkboxes = document.querySelectorAll('#permissions-list input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (!groupId) return;
    
    // Get the selected option and its permissions
    const select = document.querySelector('select[name="group_id"]');
    const selectedOption = select.options[select.selectedIndex];
    const permissions = JSON.parse(selectedOption.getAttribute('data-permissions') || '[]');
    
    // Check the boxes for existing permissions
    permissions.forEach(permissionId => {
        const checkbox = document.querySelector(`input[data-permission-id="${permissionId}"]`);
        if (checkbox) {
            checkbox.checked = true;
        }
    });
}
</script>