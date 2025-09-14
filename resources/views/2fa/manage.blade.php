@extends('layouts.app')

@section('title', '2FA Verwaltung')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-600 to-blue-600 px-6 py-8 text-center">
                <div class="mx-auto h-16 w-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mb-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.5-2.5l2.5-2.5m0 0l-5.5 5.5M21 7l-5.5 5.5"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">2FA ist aktiv</h1>
                <p class="text-green-100">Ihr Konto ist durch Zwei-Faktor-Authentifizierung geschützt</p>
            </div>

            <div class="px-6 py-8">
                <!-- Success Messages -->
                @if (session('success'))
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-md">
                        <div class="flex">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <p class="ml-3 text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                <!-- Recovery Codes Display -->
                @if (session('recovery_codes'))
                    <div class="mb-8 p-6 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-center mb-4">
                            <svg class="h-6 w-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-3.586l6.257-6.257C9.743 9.743 10 9.257 10 8.657V6.5A2.5 2.5 0 0112.5 4h3A2.5 2.5 0 0118 6.5V7z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-yellow-800">Recovery-Codes - Sicher aufbewahren!</h3>
                        </div>
                        <p class="text-yellow-800 mb-4">
                            Diese Codes können verwendet werden, falls Ihr Authenticator nicht verfügbar ist.
                            Jeder Code kann nur einmal verwendet werden.
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (session('recovery_codes') as $code)
                                <div class="bg-white p-3 rounded font-mono text-center text-sm border">
                                    {{ $code }}
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 flex space-x-3">
                            <button onclick="printRecoveryCodes()" class="flex-1 py-2 px-4 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors text-sm">
                                🖨️ Drucken
                            </button>
                            <button onclick="downloadRecoveryCodes()" class="flex-1 py-2 px-4 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors text-sm">
                                💾 Download
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Error Messages -->
                @if ($errors->any())
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-md">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-3">
                                @foreach ($errors->all() as $error)
                                    <p class="text-sm text-red-700">{{ $error }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Status Overview -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Sicherheitsstatus</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-green-800">2FA Aktiviert</h3>
                                    <p class="text-sm text-green-600">Ihr Konto ist gesichert</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-3.586l6.257-6.257C9.743 9.743 10 9.257 10 8.657V6.5A2.5 2.5 0 0112.5 4h3A2.5 2.5 0 0118 6.5V7z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Recovery-Codes</h3>
                                    <p class="text-sm text-blue-600">{{ $recoveryCodes }} verfügbar</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Actions -->
                <div class="space-y-6">
                    <!-- Regenerate Recovery Codes -->
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Recovery-Codes erneuern</h3>
                        <p class="text-gray-600 mb-4">
                            Generieren Sie neue Recovery-Codes. Die alten Codes werden dadurch ungültig.
                        </p>
                        <button onclick="showRegenerateForm()" class="py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            🔄 Neue Codes generieren
                        </button>

                        <!-- Hidden Form -->
                        <form id="regenerate-form" method="POST" action="{{ route('2fa.regenerate-recovery') }}" class="hidden mt-4 pt-4 border-t">
                            @csrf
                            <div class="mb-4">
                                <label for="regenerate-password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Passwort bestätigen
                                </label>
                                <input id="regenerate-password" name="password" type="password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex space-x-3">
                                <button type="button" onclick="hideRegenerateForm()"
                                        class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                                    Abbrechen
                                </button>
                                <button type="submit"
                                        class="py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                    Bestätigen
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Disable 2FA -->
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-red-800 mb-2">2FA deaktivieren</h3>
                        <p class="text-red-600 mb-4">
                            ⚠️ <strong>Warnung:</strong> Das Deaktivieren der 2FA reduziert die Sicherheit Ihres Kontos erheblich.
                        </p>
                        <button onclick="showDisableForm()" class="py-2 px-4 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                            🚫 2FA deaktivieren
                        </button>

                        <!-- Hidden Form -->
                        <form id="disable-form" method="POST" action="{{ route('2fa.disable') }}" class="hidden mt-4 pt-4 border-t border-red-300">
                            @csrf
                            <div class="mb-4">
                                <label for="disable-password" class="block text-sm font-medium text-red-700 mb-2">
                                    Passwort bestätigen
                                </label>
                                <input id="disable-password" name="password" type="password" required
                                       class="w-full px-3 py-2 border border-red-300 rounded-md focus:ring-red-500 focus:border-red-500">
                                <p class="mt-1 text-xs text-red-600">
                                    Diese Aktion kann nicht rückgängig gemacht werden
                                </p>
                            </div>
                            <div class="flex space-x-3">
                                <button type="button" onclick="hideDisableForm()"
                                        class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                                    Abbrechen
                                </button>
                                <button type="submit"
                                        class="py-2 px-4 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                                    Endgültig deaktivieren
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-500">
                        Ihre 2FA-Einstellungen werden verschlüsselt gespeichert
                    </p>
                    <a href="{{ route('admin') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                        ← Zurück zum Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showRegenerateForm() {
    document.getElementById('regenerate-form').classList.remove('hidden');
    document.getElementById('regenerate-password').focus();
}

function hideRegenerateForm() {
    document.getElementById('regenerate-form').classList.add('hidden');
}

function showDisableForm() {
    if (confirm('Sind Sie sicher, dass Sie 2FA deaktivieren möchten? Dies reduziert die Sicherheit Ihres Kontos.')) {
        document.getElementById('disable-form').classList.remove('hidden');
        document.getElementById('disable-password').focus();
    }
}

function hideDisableForm() {
    document.getElementById('disable-form').classList.add('hidden');
}

function printRecoveryCodes() {
    const codes = @json(session('recovery_codes', []));
    if (codes.length === 0) return;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>2FA Recovery-Codes</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .codes { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 30px; }
                .code { border: 1px solid #ddd; padding: 10px; text-align: center; font-family: monospace; }
                .warning { background: #fffbdd; padding: 15px; border: 1px solid #ffc107; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>SSO Server - Recovery-Codes</h1>
                <p>Benutzer: {{ auth()->user()->name }}</p>
                <p>Generiert am: ${new Date().toLocaleString('de-DE')}</p>
            </div>
            <div class="codes">
                ${codes.map(code => '<div class="code">' + code + '</div>').join('')}
            </div>
            <div class="warning">
                <strong>Wichtige Hinweise:</strong>
                <ul>
                    <li>Bewahren Sie diese Codes sicher auf</li>
                    <li>Jeder Code kann nur einmal verwendet werden</li>
                    <li>Verwenden Sie diese Codes nur als Notfallzugang</li>
                    <li>Bei Verlust können neue Codes generiert werden</li>
                </ul>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

function downloadRecoveryCodes() {
    const codes = @json(session('recovery_codes', []));
    if (codes.length === 0) return;

    const content = `SSO Server - Recovery-Codes
Benutzer: {{ auth()->user()->name }}
Generiert am: ${new Date().toLocaleString('de-DE')}

Recovery-Codes:
${codes.map((code, index) => `${index + 1}. ${code}`).join('\n')}

WICHTIGE HINWEISE:
- Bewahren Sie diese Codes sicher auf
- Jeder Code kann nur einmal verwendet werden
- Verwenden Sie diese Codes nur als Notfallzugang
- Bei Verlust können neue Codes generiert werden`;

    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sso-recovery-codes.txt';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>
@endsection