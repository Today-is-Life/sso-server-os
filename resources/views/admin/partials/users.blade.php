<div>
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Benutzer verwalten</h1>
            <button class="btn" onclick="showCreateUserModal()">+ Neuer Benutzer</button>
        </div>
        
        <!-- Search -->
        <div style="margin-bottom: 1rem;">
            <input type="text" 
                   class="form-control" 
                   placeholder="Suche nach Name oder E-Mail..."
                   hx-get="/admin/users"
                   hx-trigger="keyup changed delay:500ms"
                   hx-target="#main-content"
                   name="search">
        </div>
        
        <!-- Users Table -->
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Gruppen</th>
                    <th>Status</th>
                    <th>Letzter Login</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @foreach($user->groups as $group)
                            <span class="badge" style="background: {{ $group->color }}; color: white;">
                                {{ $group->name }}
                            </span>
                        @endforeach
                    </td>
                    <td>
                        @if($user->email_verified_at)
                            <span class="badge badge-success">Aktiv</span>
                        @else
                            <span class="badge badge-danger">Unverifiziert</span>
                        @endif
                        @if($user->locked_until && $user->locked_until->isFuture())
                            <span class="badge badge-danger">Gesperrt</span>
                        @endif
                    </td>
                    <td>
                        {{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : 'Nie' }}
                    </td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm" onclick="editUser('{{ $user->id }}')">Bearbeiten</button>
                            @if($user->id !== Auth::id())
                            <button class="btn btn-sm btn-danger" 
                                    hx-delete="/admin/users/{{ $user->id }}"
                                    hx-confirm="Benutzer wirklich löschen?"
                                    hx-target="#main-content">
                                Löschen
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <!-- Pagination -->
        @if($users->hasPages())
        <div style="margin-top: 1rem;">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>

<script>
function showCreateUserModal() {
    // Simple modal for user creation
    const modal = document.createElement('div');
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;';
    modal.innerHTML = `
        <div style="background: white; padding: 2rem; border-radius: 0.75rem; width: 500px;">
            <h2>Neuer Benutzer</h2>
            <form hx-post="/admin/users" hx-target="#main-content">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">E-Mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Passwort</label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn">Erstellen</button>
                    <button type="button" class="btn btn-secondary" onclick="this.closest('[style*=fixed]').remove()">Abbrechen</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

function editUser(userId) {
    // Navigate to edit page using HTMX
    htmx.ajax('GET', '/admin/users/' + userId + '/edit', {
        target: '#main-content',
        swap: 'innerHTML'
    });
}
</script>