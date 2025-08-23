<div>
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Gruppen verwalten</h1>
            <button class="btn" onclick="showCreateGroupModal()">+ Neue Gruppe</button>
        </div>
        
        @if($isSuperadmin)
        <div class="alert alert-success">
            Als Superadmin sehen Sie alle Gruppen über alle Domains hinweg.
        </div>
        @endif
        
        <!-- Groups Tree -->
        <div class="group-tree" data-parent-id="">
            @foreach($groups as $group)
                @include('admin.partials.group-node', ['group' => $group])
            @endforeach
        </div>
    </div>
</div>

<script>
function showCreateGroupModal() {
    const modal = document.createElement('div');
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000;';
    modal.innerHTML = `
        <div style="background: white; padding: 2rem; border-radius: 0.75rem; width: 500px;">
            <h2>Neue Gruppe</h2>
            <form hx-post="/admin/groups" hx-target="#main-content">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beschreibung</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Farbe</label>
                    <input type="color" name="color" class="form-control" value="#667eea">
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

function toggleGroup(groupId) {
    const children = document.querySelector(`[data-group-parent="${groupId}"]`);
    if (children) {
        children.style.display = children.style.display === 'none' ? 'block' : 'none';
    }
}
</script>