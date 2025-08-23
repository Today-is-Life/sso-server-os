<div>
    <h1 class="card-title" style="margin-bottom: 2rem;">Dashboard</h1>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value" hx-get="/api/admin/stats/users" hx-trigger="load" hx-swap="innerHTML">-</div>
            <div class="stat-label">Benutzer</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value" hx-get="/api/admin/stats/groups" hx-trigger="load" hx-swap="innerHTML">-</div>
            <div class="stat-label">Gruppen</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value" hx-get="/api/admin/stats/domains" hx-trigger="load" hx-swap="innerHTML">-</div>
            <div class="stat-label">Domains</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value" hx-get="/api/admin/stats/sessions" hx-trigger="load" hx-swap="innerHTML">-</div>
            <div class="stat-label">Aktive Sessions</div>
        </div>
    </div>
    
    <!-- Recent Logins -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Letzte Anmeldungen</h2>
            <button class="btn btn-sm" hx-get="/admin/dashboard" hx-target="#main-content">
                Aktualisieren
            </button>
        </div>
        
        <div hx-get="/api/admin/recent-logins" 
             hx-trigger="load, every 30s"
             hx-swap="innerHTML">
            <div class="htmx-indicator">Lade...</div>
        </div>
    </div>
</div>