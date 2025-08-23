<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use App\Services\SIEM\SIEMService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AnomalyDetectionService
{
    private SIEMService $siem;

    /** @var array<string, mixed> */
    private array $geoIpCache = [];

    public function __construct(SIEMService $siem)
    {
        $this->siem = $siem;
    }

    /**
     * Main anomaly detection for login
     *
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    public function detectLoginAnomalies(User $user, array $context): array
    {
        $anomalies = [];

        // 1. Impossible Travel Detection
        $travelAnomaly = $this->checkImpossibleTravel($user->id, $context);
        if ($travelAnomaly) {
            $anomalies[] = $travelAnomaly;
        }

        // 2. Unusual Login Time
        $timeAnomaly = $this->checkUnusualLoginTime($user->id, $context);
        if ($timeAnomaly) {
            $anomalies[] = $timeAnomaly;
        }

        // 3. New Device Detection
        $deviceAnomaly = $this->checkNewDevice($user->id, $context);
        if ($deviceAnomaly) {
            $anomalies[] = $deviceAnomaly;
        }

        // 4. Concurrent Sessions
        $sessionAnomaly = $this->checkConcurrentSessions($user->id, $context);
        if ($sessionAnomaly) {
            $anomalies[] = $sessionAnomaly;
        }

        // 5. Brute Force Detection
        $bruteForceAnomaly = $this->checkBruteForce($user->id, $context);
        if ($bruteForceAnomaly) {
            $anomalies[] = $bruteForceAnomaly;
        }

        // 6. Tor/Proxy Detection
        $proxyAnomaly = $this->checkTorProxy($context['ip']);
        if ($proxyAnomaly) {
            $anomalies[] = $proxyAnomaly;
        }

        // Process anomalies
        if (! empty($anomalies)) {
            $this->handleAnomalies($user, $anomalies);
        }

        return $anomalies;
    }

    /**
     * Impossible Travel Detection
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function checkImpossibleTravel(string $userId, array $context): ?array
    {
        $lastLogin = Cache::get("last_login_location_{$userId}");
        if (! $lastLogin) {
            // Store current location for next check
            Cache::put("last_login_location_{$userId}", [
                'ip' => $context['ip'],
                'location' => $this->getGeoLocation($context['ip']),
                'timestamp' => time(),
            ], 86400);

            return null;
        }

        $currentLocation = $this->getGeoLocation($context['ip']);
        $timeDiff = time() - $lastLogin['timestamp'];
        $distance = $this->calculateDistance($lastLogin['location'], $currentLocation);

        // Max speed: 900 km/h (airplane)
        $maxPossibleDistance = ($timeDiff / 3600) * 900;

        if ($distance > $maxPossibleDistance && $timeDiff < 7200) { // Within 2 hours
            return [
                'type' => 'impossible_travel',
                'severity' => 'critical',
                'confidence' => min(100, ($distance / $maxPossibleDistance) * 100),
                'details' => [
                    'distance_km' => round($distance),
                    'time_minutes' => round($timeDiff / 60),
                    'from_location' => $lastLogin['location']['city'] ?? 'Unknown',
                    'to_location' => $currentLocation['city'] ?? 'Unknown',
                    'from_ip' => $lastLogin['ip'],
                    'to_ip' => $context['ip'],
                ],
            ];
        }

        // Update location for next check
        Cache::put("last_login_location_{$userId}", [
            'ip' => $context['ip'],
            'location' => $currentLocation,
            'timestamp' => time(),
        ], 86400);

        return null;
    }

    /**
     * Check unusual login time based on user pattern
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function checkUnusualLoginTime(string $userId, array $context): ?array
    {
        $loginHistory = DB::table('user_sessions')
            ->where('user_id', $userId)
            ->where('created_at', '>', now()->subDays(30))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->get();

        if ($loginHistory->isEmpty()) {
            return null;
        }

        // Calculate normal hours (where user has > 10% of logins)
        $totalLogins = $loginHistory->sum('count');
        $normalHours = $loginHistory
            ->filter(fn ($h) => ($h->count / $totalLogins) > 0.1)
            ->pluck('hour')
            ->toArray();

        $currentHour = (int) date('H');

        if (! empty($normalHours) && ! in_array($currentHour, $normalHours)) {
            return [
                'type' => 'unusual_time',
                'severity' => 'warning',
                'confidence' => 70,
                'details' => [
                    'current_hour' => $currentHour,
                    'normal_hours' => $normalHours,
                    'timezone' => $context['timezone'] ?? 'UTC',
                ],
            ];
        }

        return null;
    }

    /**
     * Check for new device
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function checkNewDevice(string $userId, array $context): ?array
    {
        $deviceFingerprint = $this->generateDeviceFingerprint($context);

        $knownDevice = DB::table('user_devices')
            ->where('user_id', $userId)
            ->where('device_fingerprint', $deviceFingerprint)
            ->exists();

        if (! $knownDevice) {
            // Check if this is a completely new device type
            $deviceInfo = $this->parseUserAgent($context['user_agent']);

            $similarDevice = DB::table('user_devices')
                ->where('user_id', $userId)
                ->where('device_type', $deviceInfo['type'])
                ->where('browser', $deviceInfo['browser'])
                ->exists();

            return [
                'type' => 'new_device',
                'severity' => $similarDevice ? 'info' : 'warning',
                'confidence' => $similarDevice ? 60 : 90,
                'details' => [
                    'device_fingerprint' => $deviceFingerprint,
                    'device_type' => $deviceInfo['type'],
                    'browser' => $deviceInfo['browser'],
                    'os' => $deviceInfo['os'],
                    'is_mobile' => $deviceInfo['is_mobile'],
                ],
            ];
        }

        return null;
    }

    /**
     * Check concurrent sessions from different locations
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function checkConcurrentSessions(string $userId, array $context): ?array
    {
        $activeSessions = DB::table('user_sessions')
            ->where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->where('ip_address', '!=', $context['ip'])
            ->get();

        if ($activeSessions->isEmpty()) {
            return null;
        }

        $currentLocation = $this->getGeoLocation($context['ip']);
        $differentCountries = [];

        foreach ($activeSessions as $session) {
            $sessionLocation = $this->getGeoLocation($session->ip_address);
            if ($sessionLocation['country'] !== $currentLocation['country']) {
                $differentCountries[] = [
                    'country' => $sessionLocation['country'],
                    'ip' => $session->ip_address,
                    'started' => $session->created_at,
                ];
            }
        }

        if (! empty($differentCountries)) {
            return [
                'type' => 'concurrent_sessions',
                'severity' => 'critical',
                'confidence' => 95,
                'details' => [
                    'active_sessions' => $activeSessions->count(),
                    'different_countries' => $differentCountries,
                    'current_country' => $currentLocation['country'],
                ],
            ];
        }

        return null;
    }

    /**
     * Check for brute force attempts
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function checkBruteForce(string $userId, array $context): ?array
    {
        $failedAttempts = Cache::get("failed_login_{$userId}", 0);
        $ipFailedAttempts = Cache::get("failed_login_ip_{$context['ip']}", 0);

        if ($failedAttempts > 5 || $ipFailedAttempts > 10) {
            return [
                'type' => 'brute_force',
                'severity' => 'critical',
                'confidence' => 100,
                'details' => [
                    'user_attempts' => $failedAttempts,
                    'ip_attempts' => $ipFailedAttempts,
                    'ip_address' => $context['ip'],
                    'lockout_recommended' => $failedAttempts > 10,
                ],
            ];
        }

        return null;
    }

    /**
     * Check if IP is Tor exit node or known proxy
     *
     * @return array<string, mixed>|null
     */
    private function checkTorProxy(string $ip): ?array
    {
        // Check Tor exit nodes
        $isTor = $this->isTorExitNode($ip);
        if ($isTor) {
            return [
                'type' => 'tor_usage',
                'severity' => 'warning',
                'confidence' => 100,
                'details' => [
                    'ip' => $ip,
                    'type' => 'tor_exit_node',
                ],
            ];
        }

        // Check known proxy/VPN services
        $proxyInfo = $this->checkProxyVPN($ip);
        if ($proxyInfo) {
            return [
                'type' => 'proxy_usage',
                'severity' => 'info',
                'confidence' => $proxyInfo['confidence'],
                'details' => [
                    'ip' => $ip,
                    'type' => $proxyInfo['type'],
                    'provider' => $proxyInfo['provider'] ?? 'Unknown',
                ],
            ];
        }

        return null;
    }

    /**
     * Get geo location for IP
     *
     * @return array<string, mixed>
     */
    private function getGeoLocation(string $ip): array
    {
        if (isset($this->geoIpCache[$ip])) {
            return $this->geoIpCache[$ip];
        }

        // Use MaxMind or IP-API service
        try {
            $response = Http::get("http://ip-api.com/json/{$ip}");
            if ($response->successful()) {
                $data = $response->json();
                $location = [
                    'country' => $data['country'] ?? 'Unknown',
                    'city' => $data['city'] ?? 'Unknown',
                    'lat' => $data['lat'] ?? 0,
                    'lon' => $data['lon'] ?? 0,
                    'isp' => $data['isp'] ?? 'Unknown',
                    'is_proxy' => $data['proxy'] ?? false,
                ];
                $this->geoIpCache[$ip] = $location;

                return $location;
            }
        } catch (\Exception $e) {
            \Log::error('GeoIP lookup failed: '.$e->getMessage());
        }

        return [
            'country' => 'Unknown',
            'city' => 'Unknown',
            'lat' => 0,
            'lon' => 0,
        ];
    }

    /**
     * Calculate distance between two coordinates
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     */
    private function calculateDistance(array $from, array $to): float
    {
        $earthRadius = 6371; // km

        $latDiff = deg2rad($to['lat'] - $from['lat']);
        $lonDiff = deg2rad($to['lon'] - $from['lon']);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($from['lat'])) * cos(deg2rad($to['lat'])) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Generate device fingerprint
     *
     * @param  array<string, mixed>  $context
     */
    private function generateDeviceFingerprint(array $context): string
    {
        $parts = [
            $context['user_agent'] ?? '',
            $context['accept_language'] ?? '',
            $context['accept_encoding'] ?? '',
            $context['screen_resolution'] ?? '',
            $context['timezone'] ?? '',
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Parse user agent
     *
     * @return array<string, mixed>
     */
    private function parseUserAgent(string $userAgent): array
    {
        // Simple parsing - in production use a proper library
        $isMobile = preg_match('/Mobile|Android|iPhone/i', $userAgent);
        $browser = 'Unknown';
        $os = 'Unknown';
        $type = $isMobile ? 'mobile' : 'desktop';

        if (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        }

        if (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS/i', $userAgent)) {
            $os = 'MacOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $os = 'iOS';
        }

        return [
            'type' => $type,
            'browser' => $browser,
            'os' => $os,
            'is_mobile' => $isMobile,
        ];
    }

    /**
     * Check if IP is Tor exit node
     */
    private function isTorExitNode(string $ip): bool
    {
        // Check against cached Tor exit node list
        $torNodes = Cache::remember('tor_exit_nodes', 3600, function () {
            try {
                $response = Http::get('https://check.torproject.org/torbulkexitlist');
                if ($response->successful()) {
                    return explode("\n", $response->body());
                }
            } catch (\Exception $e) {
                \Log::error('Failed to fetch Tor exit nodes: '.$e->getMessage());
            }

            return [];
        });

        return in_array($ip, $torNodes);
    }

    /**
     * Check if IP is known proxy/VPN
     *
     * @return array<string, mixed>|null
     */
    private function checkProxyVPN(string $ip): ?array
    {
        // Check against known VPN/proxy ranges
        // This would use a commercial service like IPQualityScore in production

        // Simple check based on ISP name
        $location = $this->getGeoLocation($ip);
        $vpnProviders = [
            'NordVPN', 'ExpressVPN', 'CyberGhost', 'Surfshark',
            'Private Internet Access', 'ProtonVPN', 'IPVanish',
        ];

        foreach ($vpnProviders as $provider) {
            if (stripos($location['isp'] ?? '', $provider) !== false) {
                return [
                    'type' => 'vpn',
                    'provider' => $provider,
                    'confidence' => 90,
                ];
            }
        }

        return null;
    }

    /**
     * Handle detected anomalies
     *
     * @param  array<int, array<string, mixed>>  $anomalies
     */
    private function handleAnomalies(User $user, array $anomalies): void
    {
        $criticalAnomalies = array_filter($anomalies, fn ($a) => $a['severity'] === 'critical');

        if (! empty($criticalAnomalies)) {
            // Require additional verification
            Cache::put("require_mfa_{$user->id}", true, 300);

            // Notify user
            $this->notifyUser($user, $anomalies);

            // Log to SIEM
            foreach ($anomalies as $anomaly) {
                $this->siem->sendSecurityEvent([
                    'event_id' => 'ANOMALY_'.strtoupper($anomaly['type']),
                    'severity' => $anomaly['severity'],
                    'user_id' => $user->id,
                    'message' => 'Anomaly detected: '.$anomaly['type'],
                    'metadata' => $anomaly,
                ]);
            }

            // Broadcast to domains for immediate action
            $this->broadcastToDomains($user, $anomalies);
        }
    }

    /**
     * Notify user about anomalies
     *
     * @param  array<int, array<string, mixed>>  $anomalies
     */
    private function notifyUser(User $user, array $anomalies): void
    {
        // Send email/SMS alert
        \Mail::raw(
            'Suspicious activity detected on your account. Please verify recent login attempts.',
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('⚠️ Security Alert - Unusual Activity Detected');
            }
        );
    }

    /**
     * Broadcast anomalies to all user's active domains
     *
     * @param  array<int, array<string, mixed>>  $anomalies
     */
    private function broadcastToDomains(User $user, array $anomalies): void
    {
        $domains = DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->distinct('domain_id')
            ->pluck('domain_id');

        foreach ($domains as $domainId) {
            $domain = \App\Models\Domain::find($domainId);
            if ($domain) {
                Http::withHeaders([
                    'X-SSO-Event' => 'user_anomaly',
                    'X-SSO-Signature' => hash_hmac('sha256', json_encode($anomalies), $domain->webhook_secret),
                ])->post($domain->url.'/sso/webhook', [
                    'event' => 'user.anomaly_detected',
                    'user_id' => $user->id,
                    'anomalies' => $anomalies,
                    'action_required' => 'verify_identity',
                ]);
            }
        }
    }
}
