<div>
    <h1 class="card-title" style="font-size: 2rem; margin-bottom: 2rem;">Dashboard</h1>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value">{{ $stats['users'] }}</div>
            <div class="stat-label">Benutzer</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $stats['groups'] }}</div>
            <div class="stat-label">Gruppen</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $stats['domains'] }}</div>
            <div class="stat-label">Domains</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $stats['active_sessions'] }}</div>
            <div class="stat-label">Aktive Sessions</div>
        </div>
    </div>
    
    <!-- Recent Users -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Neue Benutzer</h2>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Registriert</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentUsers as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->created_at->format('d.m.Y H:i') }}</td>
                    <td>
                        @if($user->email_verified_at)
                            <span class="badge badge-success">Verifiziert</span>
                        @else
                            <span class="badge badge-danger">Unverifiziert</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #718096;">Keine Benutzer vorhanden</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if(Auth::user()->hasPermission('system.manage'))
    <!-- Quick Access for Superadmins -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Domain Quick Access</h2>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
            @php
                $domains = \App\Models\Domain::where('is_active', true)->get();
            @endphp
            @foreach($domains as $domain)
            <a href="{{ $domain->url }}/admin" 
               target="_blank"
               class="btn btn-secondary"
               style="text-align: center;">
                <div>{{ $domain->display_name }}</div>
                <div style="font-size: 0.75rem; opacity: 0.8;">{{ parse_url($domain->url, PHP_URL_HOST) }}</div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>