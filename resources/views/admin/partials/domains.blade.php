<div>
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Domains verwalten</h1>
            <button hx-get="/admin/domains/create"
                    hx-target="#main-content"
                    hx-push-url="true"
                    class="btn">+ Neue Domain</button>
        </div>
        
        <!-- Domains Table -->
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Display Name</th>
                    <th>URL</th>
                    <th>Gruppen</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($domains as $domain)
                <tr>
                    <td>{{ $domain->name }}</td>
                    <td>{{ $domain->display_name }}</td>
                    <td>
                        <a href="{{ $domain->url }}" target="_blank" style="color: var(--primary);">
                            {{ $domain->url }}
                        </a>
                    </td>
                    <td>{{ $domain->groups_count }}</td>
                    <td>
                        @if($domain->is_active)
                            <span class="badge badge-success">Aktiv</span>
                        @else
                            <span class="badge badge-danger">Inaktiv</span>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="{{ $domain->url }}/admin" target="_blank" class="btn btn-sm">
                                Admin öffnen
                            </a>
                            <button hx-get="/admin/domains/{{ $domain->id }}/edit"
                                    hx-target="#main-content"
                                    hx-push-url="true"
                                    class="btn btn-sm">Bearbeiten</button>
                            <button class="btn btn-sm btn-danger"
                                    hx-delete="/admin/domains/{{ $domain->id }}"
                                    hx-confirm="Domain wirklich löschen? Alle zugehörigen Daten werden ebenfalls gelöscht!"
                                    hx-target="#main-content">
                                Löschen
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>