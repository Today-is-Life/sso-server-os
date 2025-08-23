<div x-data="{ showCreateForm: false, selectedUser: null }">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Benutzer verwalten</h1>
            <button class="btn btn-success" @click="showCreateForm = !showCreateForm">
                + Neuer Benutzer
            </button>
        </div>
        
        <!-- Create User Form -->
        <div x-show="showCreateForm" x-transition class="card" style="background: #f7fafc;">
            <form hx-post="/api/admin/users" 
                  hx-target="#users-list"
                  hx-swap="innerHTML"
                  hx-ext="json-enc"
                  @htmx:after-request="showCreateForm = false">
                
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
                
                <div class="form-group">
                    <label class="form-label">Gruppen</label>
                    <div hx-get="/api/admin/groups/select" 
                         hx-trigger="load"
                         hx-swap="innerHTML">
                        <select name="groups[]" class="form-control" multiple style="height: 120px;">
                            <option>Lade Gruppen...</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="verify_email" value="1">
                        E-Mail als verifiziert markieren
                    </label>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">Benutzer erstellen</button>
                    <button type="button" class="btn btn-secondary" @click="showCreateForm = false">Abbrechen</button>
                </div>
            </form>
        </div>
        
        <!-- Search -->
        <div class="form-group">
            <input type="search" 
                   class="form-control" 
                   placeholder="Suche nach Name oder E-Mail..."
                   hx-get="/api/admin/users/search"
                   hx-trigger="keyup changed delay:500ms"
                   hx-target="#users-list"
                   name="search">
        </div>
    </div>
    
    <!-- Users List -->
    <div class="card">
        <div id="users-list" 
             hx-get="/api/admin/users/list" 
             hx-trigger="load"
             hx-swap="innerHTML">
            <div class="htmx-indicator">Lade Benutzer...</div>
        </div>
    </div>
</div>