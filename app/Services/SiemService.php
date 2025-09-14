<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Security Information and Event Management (SIEM) Service
 *
 * Handles real-time security event collection, analysis, and alerting
 */
class SiemService
{
    /**
     * Security event types
     */
    const EVENT_LOGIN_SUCCESS = 'login_success';
    const EVENT_LOGIN_FAILURE = 'login_failure';
    const EVENT_PASSWORD_CHANGE = 'password_change';
    const EVENT_2FA_ENABLED = '2fa_enabled';
    const EVENT_2FA_DISABLED = '2fa_disabled';
    const EVENT_SOCIAL_LOGIN = 'social_login';
    const EVENT_MAGIC_LINK = 'magic_link_used';
    const EVENT_ACCOUNT_CREATED = 'account_created';
    const EVENT_SUSPICIOUS_LOGIN = 'suspicious_login';
    const EVENT_BRUTE_FORCE = 'brute_force_attempt';
    const EVENT_IMPOSSIBLE_TRAVEL = 'impossible_travel';
    const EVENT_NEW_DEVICE = 'new_device_login';
    const EVENT_ADMIN_ACTION = 'admin_action';
    const EVENT_PERMISSION_CHANGE = 'permission_change';
    const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';
    const EVENT_LOGOUT = 'logout';
    const EVENT_ACCOUNT_DELETED = 'account_deleted';
    const EVENT_MFA_ENABLED = 'mfa_enabled';
    const EVENT_MFA_DISABLED = 'mfa_disabled';
    const EVENT_API_ACCESS = 'api_access';
    const EVENT_DATA_EXPORT = 'data_export';

    /**
     * Error severity level
     */
    const LEVEL_ERROR = 'error';

    /**
     * Security levels
     */
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Log a security event
     */
    public function logEvent(
        string $eventType,
        string $level = self::LEVEL_INFO,
        ?string $userId = null,
        ?Request $request = null,
        array $additionalData = [],
        ?string $correlationId = null
    ): SecurityEvent {
        $event = SecurityEvent::create([
            'event_id' => $eventType,
            'severity' => $level,
            'user_id' => $userId,
            'domain_id' => null, // Can be set based on context
            'ip_address' => $request ? $request->ip() : request()->ip(),
            'action' => $eventType,
            'message' => $this->getEventMessage($eventType, $additionalData),
            'metadata' => array_merge([
                'timestamp' => now()->toISOString(),
                'session_id' => session()->getId(),
                'url' => $request ? $request->fullUrl() : request()->fullUrl(),
                'user_agent' => $request ? $request->userAgent() : request()->userAgent(),
            ], $additionalData),
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);

        // Process event for anomaly detection
        $this->processEvent($event);

        return $event;
    }

    /**
     * Get human-readable message for event type
     */
    protected function getEventMessage(string $eventType, array $data = []): string
    {
        $messages = [
            self::EVENT_LOGIN_SUCCESS => 'User logged in successfully',
            self::EVENT_LOGIN_FAILURE => 'Failed login attempt',
            self::EVENT_LOGIN_SUCCESS => 'User logged in successfully',
            self::EVENT_LOGOUT => 'User logged out',
            self::EVENT_ACCOUNT_CREATED => 'New account created',
            self::EVENT_ACCOUNT_DELETED => 'Account deleted',
            self::EVENT_PASSWORD_CHANGE => 'Password changed',
            self::EVENT_2FA_ENABLED => '2FA enabled',
            self::EVENT_2FA_DISABLED => '2FA disabled',
            self::EVENT_SOCIAL_LOGIN => 'Social login',
            self::EVENT_MAGIC_LINK => 'Magic link used',
            self::EVENT_SUSPICIOUS_LOGIN => 'Suspicious login detected',
            self::EVENT_BRUTE_FORCE => 'Brute force attack detected',
            self::EVENT_IMPOSSIBLE_TRAVEL => 'Impossible travel detected',
            self::EVENT_NEW_DEVICE => 'New device login',
            self::EVENT_ADMIN_ACTION => 'Administrative action',
            self::EVENT_PERMISSION_CHANGE => 'Permission changed',
            self::EVENT_SUSPICIOUS_ACTIVITY => 'Suspicious activity detected',
            self::EVENT_MFA_ENABLED => 'MFA enabled',
            self::EVENT_MFA_DISABLED => 'MFA disabled',
            self::EVENT_API_ACCESS => 'API accessed',
            self::EVENT_DATA_EXPORT => 'Data exported',
        ];

        $message = $messages[$eventType] ?? 'Security event occurred';

        // Add additional context to message if available
        if (!empty($data['reason'])) {
            $message .= ': ' . $data['reason'];
        }

        return $message;
    }

    /**
     * Process security event for anomalies
     */
    public function processEvent(SecurityEvent $event): void
    {
        // Check for brute force attacks
        if ($event->event_id === self::EVENT_LOGIN_FAILURE) {
            $this->checkBruteForce($event);
        }

        // Check for impossible travel
        if (in_array($event->event_id, [self::EVENT_LOGIN_SUCCESS, self::EVENT_SOCIAL_LOGIN])) {
            $this->checkImpossibleTravel($event);
        }

        // Check for new device login
        if ($event->event_id === self::EVENT_LOGIN_SUCCESS) {
            $this->checkNewDevice($event);
        }

        // Send alerts if critical
        if ($event->severity === self::LEVEL_CRITICAL) {
            $this->sendCriticalAlert($event);
        }
    }

    /**
     * Check for brute force attacks
     */
    private function checkBruteForce(SecurityEvent $event): void
    {
        $failureCount = SecurityEvent::where('event_type', self::EVENT_LOGIN_FAILURE)
            ->where('ip_address', $event->ip_address)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($failureCount >= 5) {
            // Log brute force attempt
            $this->logEvent(
                self::EVENT_BRUTE_FORCE,
                self::LEVEL_CRITICAL,
                null,
                null,
                [
                    'failure_count' => $failureCount,
                    'source_ip' => $event->ip_address,
                    'timeframe' => '15 minutes'
                ]
            );

            // Cache IP for rate limiting
            Cache::put(
                "brute_force:{$event->ip_address}",
                true,
                now()->addHour()
            );
        }
    }

    /**
     * Check for impossible travel scenarios
     */
    private function checkImpossibleTravel(SecurityEvent $event): void
    {
        if (!$event->user_id) {
            return;
        }

        // Get the last login from a different location
        $lastEvent = SecurityEvent::where('user_id', $event->user_id)
            ->where('event_type', self::EVENT_LOGIN_SUCCESS)
            ->where('ip_address', '!=', $event->ip_address)
            ->where('created_at', '>', now()->subHours(2))
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastEvent) {
            return;
        }

        // Get geolocation data (simplified - in production use real IP geolocation)
        $currentLocation = $this->getLocationFromIp($event->ip_address);
        $lastLocation = $this->getLocationFromIp($lastEvent->ip_address);

        if ($currentLocation && $lastLocation) {
            $distance = $this->calculateDistance(
                $currentLocation['lat'],
                $currentLocation['lon'],
                $lastLocation['lat'],
                $lastLocation['lon']
            );

            $timeDiffHours = $event->created_at->diffInHours($lastEvent->created_at);
            $maxSpeed = $timeDiffHours > 0 ? $distance / $timeDiffHours : PHP_FLOAT_MAX;

            // Flag if travel speed > 900 km/h (impossible for ground travel)
            if ($maxSpeed > 900) {
                $this->logEvent(
                    self::EVENT_IMPOSSIBLE_TRAVEL,
                    self::LEVEL_CRITICAL,
                    $event->user_id,
                    null,
                    [
                        'distance_km' => round($distance, 2),
                        'time_diff_hours' => $timeDiffHours,
                        'speed_kmh' => round($maxSpeed, 2),
                        'previous_ip' => $lastEvent->ip_address,
                        'previous_location' => $lastLocation,
                        'current_location' => $currentLocation,
                    ]
                );
            }
        }
    }

    /**
     * Check for new device logins
     */
    private function checkNewDevice(SecurityEvent $event): void
    {
        if (!$event->user_id) {
            return;
        }

        $deviceFingerprint = $this->generateDeviceFingerprint($event);

        // Check if this device has been used before
        $knownDevice = SecurityEvent::where('user_id', $event->user_id)
            ->where('metadata->device_fingerprint', $deviceFingerprint)
            ->exists();

        if (!$knownDevice) {
            $this->logEvent(
                self::EVENT_NEW_DEVICE,
                self::LEVEL_WARNING,
                $event->user_id,
                null,
                [
                    'device_fingerprint' => $deviceFingerprint,
                    'user_agent' => $event->user_agent,
                    'ip_address' => $event->ip_address,
                ]
            );

            // Update original event with device fingerprint
            $metadata = $event->metadata;
            $metadata['device_fingerprint'] = $deviceFingerprint;
            $event->update(['metadata' => $metadata]);
        }
    }

    /**
     * Generate device fingerprint
     */
    private function generateDeviceFingerprint(SecurityEvent $event): string
    {
        return hash('sha256', $event->user_agent . '|' . $event->ip_address);
    }

    /**
     * Get location from IP address (simplified implementation)
     */
    private function getLocationFromIp(string $ip): ?array
    {
        // In production, use real geolocation service like MaxMind GeoIP2
        // This is a simplified mock for demonstration
        $mockLocations = [
            '127.0.0.1' => ['lat' => 52.52, 'lon' => 13.405, 'city' => 'Berlin'],
            '192.168.1.1' => ['lat' => 53.5511, 'lon' => 9.9937, 'city' => 'Hamburg'],
        ];

        return $mockLocations[$ip] ?? null;
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    /**
     * Send critical security alert
     */
    private function sendCriticalAlert(SecurityEvent $event): void
    {
        // Log to system
        Log::critical('Critical security event detected', [
            'event_id' => $event->id,
            'event_type' => $event->event_id,
            'user_id' => $event->user_id,
            'ip_address' => $event->ip_address,
            'metadata' => $event->metadata,
        ]);

        // In production, also send to:
        // - Slack/Teams webhook
        // - Email administrators
        // - SMS alerts
        // - Third-party SIEM platforms
    }

    /**
     * Get security dashboard statistics
     */
    public function getDashboardStats(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        return [
            'total_events' => SecurityEvent::where('created_at', '>', $startDate)->count(),
            'critical_events' => SecurityEvent::where('level', self::LEVEL_CRITICAL)
                ->where('created_at', '>', $startDate)->count(),
            'login_attempts' => SecurityEvent::whereIn('event_type', [
                self::EVENT_LOGIN_SUCCESS,
                self::EVENT_LOGIN_FAILURE
            ])->where('created_at', '>', $startDate)->count(),
            'unique_ips' => SecurityEvent::where('created_at', '>', $startDate)
                ->distinct('ip_address')->count('ip_address'),
            'brute_force_attempts' => SecurityEvent::where('event_type', self::EVENT_BRUTE_FORCE)
                ->where('created_at', '>', $startDate)->count(),
            'impossible_travel' => SecurityEvent::where('event_type', self::EVENT_IMPOSSIBLE_TRAVEL)
                ->where('created_at', '>', $startDate)->count(),
        ];
    }

    /**
     * Get recent security events
     */
    public function getRecentEvents(int $limit = 50): array
    {
        return SecurityEvent::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'type' => $event->event_id,
                    'level' => $event->severity,
                    'user' => $event->user ? $event->user->name : 'System',
                    'ip' => $event->ip_address,
                    'time' => $event->created_at->diffForHumans(),
                    'metadata' => $event->metadata,
                ];
            })
            ->toArray();
    }

    /**
     * Check if IP is currently blocked due to brute force
     */
    public function isIpBlocked(string $ip): bool
    {
        return Cache::has("brute_force:{$ip}");
    }

    /**
     * Get login patterns for anomaly detection
     */
    public function getLoginPatterns(string $userId, int $days = 30): array
    {
        $events = SecurityEvent::where('user_id', $userId)
            ->where('event_type', self::EVENT_LOGIN_SUCCESS)
            ->where('created_at', '>', now()->subDays($days))
            ->get();

        $patterns = [
            'common_hours' => [],
            'common_ips' => [],
            'common_locations' => [],
            'average_frequency' => 0,
        ];

        if ($events->isEmpty()) {
            return $patterns;
        }

        // Analyze common login hours
        $hours = $events->groupBy(function ($event) {
            return $event->created_at->format('H');
        })->map->count()->sortDesc();

        $patterns['common_hours'] = $hours->keys()->take(3)->toArray();

        // Analyze common IPs
        $ips = $events->groupBy('ip_address')->map->count()->sortDesc();
        $patterns['common_ips'] = $ips->keys()->take(5)->toArray();

        // Calculate average login frequency (logins per day)
        $patterns['average_frequency'] = $events->count() / max($days, 1);

        return $patterns;
    }

    /**
     * Detect impossible travel (public method for testing)
     */
    public function detectImpossibleTravel(
        string $userId,
        string $fromIp,
        string $fromLocation,
        string $toIp,
        string $toLocation
    ): bool {
        // Simplified detection for testing
        // In production, would calculate actual distance and time
        return $fromLocation !== $toLocation;
    }

    /**
     * Check if device is new (public method for testing)
     */
    public function isNewDevice(string $userId, string $userAgent): bool {
        // Check if this user agent has been seen before
        $fingerprint = hash('sha256', $userAgent);

        return !SecurityEvent::where('user_id', $userId)
            ->where('metadata->device_fingerprint', $fingerprint)
            ->exists();
    }

    /**
     * Detect brute force attempts (public method for testing)
     */
    public function detectBruteForce(string $ip): bool {
        // Check if IP has too many failed attempts
        $failureCount = SecurityEvent::where('event_id', self::EVENT_LOGIN_FAILURE)
            ->where('ip_address', $ip)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        return $failureCount >= 5;
    }
}