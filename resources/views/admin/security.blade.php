@extends('layouts.admin')

@section('title', 'Sicherheit & Ereignisse')

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Sicherheits-Dashboard</h1>
            <p class="mt-2 text-gray-600">Überwachen Sie Sicherheitsereignisse und Anomalien in Echtzeit</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-md">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Ereignisse (7T)</h3>
                        <p class="text-2xl font-bold text-blue-600" id="total-events">-</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-md">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Kritische Ereignisse</h3>
                        <p class="text-2xl font-bold text-red-600" id="critical-events">-</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-md">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Login-Versuche</h3>
                        <p class="text-2xl font-bold text-green-600" id="login-attempts">-</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 rounded-md">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Eindeutige IPs</h3>
                        <p class="text-2xl font-bold text-orange-600" id="unique-ips">-</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Security Events -->
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Aktuelle Sicherheitsereignisse</h2>
                </div>
                <div class="p-6">
                    <div id="recent-events" class="space-y-4">
                        <div class="text-center py-8 text-gray-500">Lade Ereignisse...</div>
                    </div>
                </div>
            </div>

            <!-- Threat Analysis -->
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Bedrohungsanalyse</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <!-- Brute Force Attempts -->
                        <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg border border-red-200">
                            <div class="flex items-center">
                                <div class="p-2 bg-red-100 rounded-md mr-3">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-medium text-red-900">Brute Force Angriffe</h3>
                                    <p class="text-sm text-red-700">Letzte 7 Tage</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-red-600" id="brute-force-count">-</span>
                        </div>

                        <!-- Impossible Travel -->
                        <div class="flex items-center justify-between p-4 bg-orange-50 rounded-lg border border-orange-200">
                            <div class="flex items-center">
                                <div class="p-2 bg-orange-100 rounded-md mr-3">
                                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-medium text-orange-900">Unmögliche Reisen</h3>
                                    <p class="text-sm text-orange-700">Verdächtige Standortwechsel</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-orange-600" id="impossible-travel-count">-</span>
                        </div>

                        <!-- New Device Logins -->
                        <div class="flex items-center justify-between p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                            <div class="flex items-center">
                                <div class="p-2 bg-yellow-100 rounded-md mr-3">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-medium text-yellow-900">Neue Geräte</h3>
                                    <p class="text-sm text-yellow-700">Erste Anmeldungen</p>
                                </div>
                            </div>
                            <span class="text-2xl font-bold text-yellow-600" id="new-devices-count">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Events Table -->
        <div class="mt-8 bg-white rounded-lg shadow-sm border">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Alle Sicherheitsereignisse</h2>
                    <div class="flex space-x-2">
                        <select id="event-filter" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="">Alle Ereignisse</option>
                            <option value="login_success">Erfolgreiche Logins</option>
                            <option value="login_failure">Fehlgeschlagene Logins</option>
                            <option value="brute_force_attempt">Brute Force</option>
                            <option value="impossible_travel">Unmögliche Reisen</option>
                            <option value="new_device_login">Neue Geräte</option>
                            <option value="2fa_enabled">2FA aktiviert</option>
                            <option value="2fa_disabled">2FA deaktiviert</option>
                        </select>
                        <button id="refresh-events" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">
                            Aktualisieren
                        </button>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zeit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ereignis</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Benutzer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP-Adresse</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stufe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody id="events-table-body" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">Lade Ereignisse...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Load dashboard data
async function loadDashboard() {
    try {
        // Load statistics
        const statsResponse = await fetch('/api/admin/security/stats');
        const stats = await statsResponse.json();

        document.getElementById('total-events').textContent = stats.total_events || 0;
        document.getElementById('critical-events').textContent = stats.critical_events || 0;
        document.getElementById('login-attempts').textContent = stats.login_attempts || 0;
        document.getElementById('unique-ips').textContent = stats.unique_ips || 0;
        document.getElementById('brute-force-count').textContent = stats.brute_force_attempts || 0;
        document.getElementById('impossible-travel-count').textContent = stats.impossible_travel || 0;
        document.getElementById('new-devices-count').textContent = stats.new_devices || 0;

        // Load recent events
        const eventsResponse = await fetch('/api/admin/security/events');
        const events = await eventsResponse.json();

        displayRecentEvents(events.slice(0, 10));
        displayEventsTable(events);

    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

function displayRecentEvents(events) {
    const container = document.getElementById('recent-events');

    if (events.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">Keine aktuellen Ereignisse</div>';
        return;
    }

    container.innerHTML = events.map(event => `
        <div class="flex items-start space-x-3 p-3 rounded-lg ${getEventBgColor(event.level)}">
            <div class="flex-shrink-0">
                ${getEventIcon(event.type)}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900">${getEventTypeLabel(event.type)}</p>
                <p class="text-sm text-gray-600">${event.user} - ${event.ip}</p>
                <p class="text-xs text-gray-500">${event.time}</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getLevelClass(event.level)}">
                ${event.level}
            </span>
        </div>
    `).join('');
}

function displayEventsTable(events) {
    const tbody = document.getElementById('events-table-body');

    if (events.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Keine Ereignisse gefunden</td></tr>';
        return;
    }

    tbody.innerHTML = events.map(event => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${event.time}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${getEventTypeLabel(event.type)}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${event.user}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${event.ip}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getLevelClass(event.level)}">
                    ${event.level}
                </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">
                ${JSON.stringify(event.metadata || {}).substring(0, 100)}...
            </td>
        </tr>
    `).join('');
}

function getEventIcon(type) {
    const icons = {
        'login_success': '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        'login_failure': '<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
        'brute_force_attempt': '<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>',
        'impossible_travel': '<svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>',
        'new_device_login': '<svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
        'social_login': '<svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
        '2fa_enabled': '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.5-2.5l2.5-2.5m0 0l-5.5 5.5M21 7l-5.5 5.5"></path></svg>',
        '2fa_disabled': '<svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>',
    };
    return icons[type] || '<svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
}

function getEventTypeLabel(type) {
    const labels = {
        'login_success': 'Erfolgreiche Anmeldung',
        'login_failure': 'Fehlgeschlagene Anmeldung',
        'brute_force_attempt': 'Brute Force Angriff',
        'impossible_travel': 'Unmögliche Reise',
        'new_device_login': 'Neue Geräteanmeldung',
        'social_login': 'Social Login',
        '2fa_enabled': '2FA aktiviert',
        '2fa_disabled': '2FA deaktiviert',
        'password_change': 'Passwort geändert',
        'account_created': 'Account erstellt',
        'magic_link_used': 'Magic Link verwendet'
    };
    return labels[type] || type;
}

function getEventBgColor(level) {
    switch(level) {
        case 'critical': return 'bg-red-50 border-red-200';
        case 'warning': return 'bg-yellow-50 border-yellow-200';
        default: return 'bg-blue-50 border-blue-200';
    }
}

function getLevelClass(level) {
    switch(level) {
        case 'critical': return 'bg-red-100 text-red-800';
        case 'warning': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-blue-100 text-blue-800';
    }
}

// Event listeners
document.getElementById('refresh-events').addEventListener('click', loadDashboard);
document.getElementById('event-filter').addEventListener('change', function() {
    // Filter events based on selection
    // This would need to be implemented with a proper API endpoint
    loadDashboard();
});

// Auto-refresh every 30 seconds
setInterval(loadDashboard, 30000);

// Load initial data
loadDashboard();
</script>
@endsection