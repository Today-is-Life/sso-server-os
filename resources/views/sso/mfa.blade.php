@extends('layouts.app')

@section('title', 'Zwei-Faktor-Authentifizierung')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto h-12 w-12 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full flex items-center justify-center mb-4">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Zwei-Faktor-Authentifizierung</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Bitte geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein
                </p>
            </div>

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-red-700">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- MFA Form -->
            <form method="POST" action="{{ route('sso.mfa.verify') }}" class="space-y-6">
                @csrf

                <div>
                    <label for="token" class="block text-sm font-medium text-gray-700 mb-2">
                        Authentifizierungscode
                    </label>
                    <div class="mt-1">
                        <input id="token" name="token" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                               autocomplete="one-time-code" required autofocus
                               placeholder="000000"
                               class="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-center text-lg font-mono tracking-widest">
                    </div>
                    <p class="mt-2 text-xs text-gray-500 text-center">
                        Der Code ist 30 Sekunden gültig und wird alle 30 Sekunden erneuert
                    </p>
                </div>

                <div>
                    <button type="submit" id="verify-btn"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span id="button-text">Code verifizieren</span>
                    </button>
                </div>
            </form>

            <!-- Recovery Options -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-4">Probleme beim Zugang?</p>
                    <div class="space-y-2">
                        <button type="button" onclick="showRecoveryForm()"
                                class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-3.586l6.257-6.257C9.743 9.743 10 9.257 10 8.657V6.5A2.5 2.5 0 0112.5 4h3A2.5 2.5 0 0118 6.5V7z"></path>
                            </svg>
                            Recovery-Code verwenden
                        </button>
                        <a href="{{ route('sso.logout') }}" method="post"
                           class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Abbrechen und abmelden
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recovery Form (Hidden by default) -->
            <div id="recovery-form" class="mt-6 hidden">
                <form method="POST" action="{{ route('sso.mfa.verify') }}" class="space-y-4 pt-4 border-t border-gray-200">
                    @csrf
                    <input type="hidden" name="recovery" value="1">
                    <div>
                        <label for="recovery_code" class="block text-sm font-medium text-gray-700">
                            Recovery-Code
                        </label>
                        <input id="recovery_code" name="token" type="text" placeholder="XXXX-XXXX-XXXX-XXXX"
                               class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-center font-mono">
                        <p class="mt-1 text-xs text-gray-500">
                            Geben Sie einen Ihrer 8-stelligen Recovery-Codes ein
                        </p>
                    </div>
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                        Recovery-Code verwenden
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <p class="text-xs text-center text-gray-500">
                Ihre Sicherheit ist unsere Priorität. Teilen Sie diese Codes niemals mit anderen.
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tokenInput = document.getElementById('token');
    const verifyBtn = document.getElementById('verify-btn');
    const buttonText = document.getElementById('button-text');

    // Auto-format token input
    tokenInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        e.target.value = value;

        // Auto-submit when 6 digits entered
        if (value.length === 6) {
            verifyBtn.disabled = true;
            buttonText.textContent = 'Verifiziere...';
            e.target.form.submit();
        }
    });

    // Auto-focus and select on page load
    tokenInput.focus();
    tokenInput.select();
});

function showRecoveryForm() {
    const recoveryForm = document.getElementById('recovery-form');
    recoveryForm.classList.toggle('hidden');

    if (!recoveryForm.classList.contains('hidden')) {
        document.getElementById('recovery_code').focus();
    }
}

// Auto-refresh page every 5 minutes to prevent session timeout
setTimeout(function() {
    window.location.reload();
}, 5 * 60 * 1000);
</script>
@endsection