@extends('layouts.app')

@section('title', '2FA Einrichtung')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-8 text-center">
                <div class="mx-auto h-16 w-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mb-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Zwei-Faktor-Authentifizierung</h1>
                <p class="text-indigo-100">Erhöhen Sie die Sicherheit Ihres Kontos</p>
            </div>

            <div class="px-6 py-8">
                <!-- Setup Steps -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-8 h-8 bg-indigo-600 text-white rounded-full text-sm font-bold mr-3">1</div>
                            <span class="text-gray-900 font-medium">App installieren</span>
                        </div>
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-8 h-8 bg-gray-300 text-gray-600 rounded-full text-sm font-bold mr-3">2</div>
                            <span class="text-gray-500">QR-Code scannen</span>
                        </div>
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-8 h-8 bg-gray-300 text-gray-600 rounded-full text-sm font-bold mr-3">3</div>
                            <span class="text-gray-500">Code bestätigen</span>
                        </div>
                    </div>
                </div>

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

                <!-- Step 1: Install App -->
                <div class="setup-step mb-8" id="step1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Schritt 1: Authenticator-App installieren
                    </h3>
                    <p class="text-gray-600 mb-4">
                        Installieren Sie eine der folgenden Apps auf Ihrem Smartphone:
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="border rounded-lg p-4 text-center">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Google Authenticator</h4>
                            <p class="text-sm text-gray-500">Kostenlos für iOS & Android</p>
                        </div>
                        <div class="border rounded-lg p-4 text-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Authy</h4>
                            <p class="text-sm text-gray-500">Mit Cloud-Sync</p>
                        </div>
                        <div class="border rounded-lg p-4 text-center">
                            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Microsoft Authenticator</h4>
                            <p class="text-sm text-gray-500">Enterprise-Features</p>
                        </div>
                    </div>
                    <button onclick="nextStep(2)" class="w-full py-2 px-4 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                        App installiert - Weiter zu Schritt 2
                    </button>
                </div>

                <!-- Step 2: Scan QR Code -->
                <div class="setup-step hidden mb-8" id="step2">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Schritt 2: QR-Code scannen
                    </h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="text-center">
                            <div class="bg-white p-4 rounded-lg border-2 border-gray-200 inline-block mb-4">
                                <img src="{{ $qrCodeUrl }}" alt="2FA QR Code" class="w-48 h-48 mx-auto">
                            </div>
                            <p class="text-sm text-gray-600">
                                Scannen Sie diesen QR-Code mit Ihrer Authenticator-App
                            </p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">So geht's:</h4>
                            <ol class="list-decimal list-inside space-y-2 text-gray-600">
                                <li>Öffnen Sie Ihre Authenticator-App</li>
                                <li>Tippen Sie auf "Konto hinzufügen" oder das Plus-Symbol</li>
                                <li>Wählen Sie "QR-Code scannen"</li>
                                <li>Richten Sie die Kamera auf den QR-Code</li>
                            </ol>

                            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                <h5 class="font-medium text-gray-900 mb-2">Manuell hinzufügen:</h5>
                                <p class="text-xs text-gray-500 mb-2">Falls der QR-Code nicht funktioniert:</p>
                                <div class="font-mono text-sm bg-white p-2 rounded border break-all">
                                    {{ $secret }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-4 mt-6">
                        <button onclick="previousStep(1)" class="flex-1 py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                            Zurück
                        </button>
                        <button onclick="nextStep(3)" class="flex-1 py-2 px-4 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                            QR-Code gescannt - Weiter
                        </button>
                    </div>
                </div>

                <!-- Step 3: Verify Code -->
                <div class="setup-step hidden" id="step3">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Schritt 3: Code bestätigen
                    </h3>
                    <p class="text-gray-600 mb-6">
                        Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein, um 2FA zu aktivieren:
                    </p>

                    <form method="POST" action="{{ route('2fa.enable') }}" class="space-y-6">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="token" class="block text-sm font-medium text-gray-700 mb-2">
                                    6-stelliger Code
                                </label>
                                <input id="token" name="token" type="text" inputmode="numeric" pattern="[0-9]*"
                                       maxlength="6" placeholder="000000" autofocus
                                       class="w-full px-3 py-3 border border-gray-300 rounded-md text-center text-lg font-mono tracking-widest focus:ring-indigo-500 focus:border-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">
                                    Der Code erneuert sich alle 30 Sekunden
                                </p>
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Aktuelles Passwort bestätigen
                                </label>
                                <input id="password" name="password" type="password" required
                                       class="w-full px-3 py-3 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="Ihr aktuelles Passwort">
                                <p class="mt-1 text-xs text-gray-500">
                                    Zur Sicherheit erforderlich
                                </p>
                            </div>
                        </div>

                        <div class="flex space-x-4">
                            <button type="button" onclick="previousStep(2)"
                                    class="flex-1 py-3 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                                Zurück
                            </button>
                            <button type="submit"
                                    class="flex-1 py-3 px-4 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors font-semibold">
                                🔐 2FA Aktivieren
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-500">
                        Ihre Daten werden verschlüsselt und sicher gespeichert
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
function nextStep(step) {
    // Hide all steps
    document.querySelectorAll('.setup-step').forEach(el => el.classList.add('hidden'));
    // Show current step
    document.getElementById('step' + step).classList.remove('hidden');

    // Update step indicators
    for (let i = 1; i <= 3; i++) {
        const indicator = document.querySelector(`div:nth-child(${i}) .w-8.h-8`);
        if (i <= step) {
            indicator.classList.remove('bg-gray-300', 'text-gray-600');
            indicator.classList.add('bg-indigo-600', 'text-white');
            indicator.nextElementSibling.classList.remove('text-gray-500');
            indicator.nextElementSibling.classList.add('text-gray-900', 'font-medium');
        }
    }

    // Focus on token input in step 3
    if (step === 3) {
        setTimeout(() => document.getElementById('token').focus(), 100);
    }
}

function previousStep(step) {
    nextStep(step);

    // Update step indicators
    for (let i = step + 1; i <= 3; i++) {
        const indicator = document.querySelector(`div:nth-child(${i}) .w-8.h-8`);
        indicator.classList.remove('bg-indigo-600', 'text-white');
        indicator.classList.add('bg-gray-300', 'text-gray-600');
        indicator.nextElementSibling.classList.remove('text-gray-900', 'font-medium');
        indicator.nextElementSibling.classList.add('text-gray-500');
    }
}

// Auto-format token input
document.addEventListener('DOMContentLoaded', function() {
    const tokenInput = document.getElementById('token');
    if (tokenInput) {
        tokenInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }
});
</script>
@endsection