<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use App\Services\SIEM\SIEMService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ZeroTrustService
{
    private SIEMService $siem;

    private AnomalyDetectionService $anomalyDetector;

    public function __construct(SIEMService $siem, AnomalyDetectionService $anomalyDetector)
    {
        $this->siem = $siem;
        $this->anomalyDetector = $anomalyDetector;
    }

    /**
     * Zero Trust: Verify every request
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function verifyRequest(array $context): array
    {
        $scores = [
            'device' => $this->calculateDeviceTrustScore($context),
            'user' => $this->calculateUserRiskScore($context),
            'network' => $this->calculateNetworkTrustScore($context),
            'behavior' => $this->calculateBehaviorScore($context),
            'context' => $this->calculateContextScore($context),
        ];

        $totalScore = array_sum($scores) / count($scores);
        $requiredScore = $this->getRequiredScore($context['action'], $context['resource']);

        $decision = [
            'allowed' => $totalScore >= $requiredScore,
            'score' => $totalScore,
            'required_score' => $requiredScore,
            'scores' => $scores,
            'recommendations' => [],
        ];

        // Add recommendations based on score
        if (! $decision['allowed']) {
            $decision['recommendations'] = $this->getRecommendations($scores, $context);
        }

        // Log decision
        $this->logDecision($context, $decision);

        // If denied, check if we should allow with step-up auth
        if (! $decision['allowed'] && $this->canStepUp($context, $totalScore, $requiredScore)) {
            $decision['step_up_required'] = true;
            $decision['step_up_methods'] = $this->getStepUpMethods($context);
        }

        return $decision;
    }

    /**
     * Calculate device trust score (0-100)
     *
     * @param  array<string, mixed>  $context
     */
    private function calculateDeviceTrustScore(array $context): int
    {
        $score = 50; // Base score

        if (! isset($context['device_id'])) {
            return 0; // Unknown device
        }

        $device = DB::table('user_devices')
            ->where('id', $context['device_id'])
            ->first();

        if (! $device) {
            return 0;
        }

        // Trusted device +30
        if ($device->is_trusted) {
            $score += 30;
        }

        // Certificate-based auth +20
        if ($context['auth_method'] === 'certificate') {
            $score += 20;
        }

        // Managed device +15
        if ($device->is_managed) {
            $score += 15;
        }

        // Recent successful auth +10
        if ($device->last_successful_auth > now()->subHours(24)) {
            $score += 10;
        }

        // No recent failures +10
        if ($device->failed_attempts === 0) {
            $score += 10;
        }

        // Jailbroken/Rooted -30
        if ($device->is_jailbroken) {
            $score -= 30;
        }

        // Old OS version -20
        if ($this->isOldOSVersion($device->os_version)) {
            $score -= 20;
        }

        // Unknown location -15
        if (! $this->isKnownLocation($context['ip'], $context['user_id'])) {
            $score -= 15;
        }

        return (int) max(0, min(100, $score));
    }

    /**
     * Calculate user risk score (0-100)
     *
     * @param  array<string, mixed>  $context
     */
    private function calculateUserRiskScore(array $context): int
    {
        $score = 70; // Base score

        $user = User::find($context['user_id']);
        if (! $user) {
            return 0;
        }

        // MFA enabled +20
        if ($user->mfa_enabled) {
            $score += 20;
        }

        // Recent password change +10
        if ($user->password_changed_at > now()->subDays(90)) {
            $score += 10;
        }

        // Account age +5 (if > 6 months)
        if ($user->created_at < now()->subMonths(6)) {
            $score += 5;
        }

        // Verified email +5
        if ($user->email_verified_at) {
            $score += 5;
        }

        // Admin/Privileged user -20 (higher risk target)
        if ($this->hasPrivilegedAccess($user)) {
            $score -= 20;
        }

        // Recent anomalies -10 per anomaly
        $recentAnomalies = Cache::get("user_anomalies_{$user->id}", 0);
        $score -= ($recentAnomalies * 10);

        // Account locked recently -30
        if ($user->locked_until && $user->locked_until->isAfter(now()->subDays(7))) {
            $score -= 30;
        }

        // Dormant account reactivated -25
        if ($this->isDormantReactivated($user)) {
            $score -= 25;
        }

        return (int) max(0, min(100, $score));
    }

    /**
     * Calculate network trust score (0-100)
     *
     * @param  array<string, mixed>  $context
     */
    private function calculateNetworkTrustScore(array $context): int
    {
        $ip = $context['ip'];

        // Corporate network
        if ($this->isCorporateNetwork($ip)) {
            return 95;
        }

        // Known home/office IP
        if ($this->isKnownUserIP($ip, $context['user_id'])) {
            return 85;
        }

        // Residential ISP
        if ($this->isResidentialISP($ip)) {
            return 60;
        }

        // Known VPN service
        if ($this->isKnownVPN($ip)) {
            return 40;
        }

        // Mobile carrier
        if ($this->isMobileCarrier($ip)) {
            return 50;
        }

        // Public WiFi
        if ($this->isPublicWiFi($ip)) {
            return 30;
        }

        // Tor exit node
        if ($this->isTorExitNode($ip)) {
            return 10;
        }

        // Suspicious/Blacklisted
        if ($this->isBlacklisted($ip)) {
            return 0;
        }

        return 35; // Unknown
    }

    /**
     * Calculate behavior score based on patterns (0-100)
     *
     * @param  array<string, mixed>  $context
     */
    private function calculateBehaviorScore(array $context): int
    {
        $score = 70;
        $userId = $context['user_id'];

        // Check velocity (too many requests)
        $requestCount = Cache::get("request_count_{$userId}", 0);
        if ($requestCount > 100) { // per minute
            $score -= 30;
        }

        // Check failed attempts
        $failedAttempts = Cache::get("failed_attempts_{$userId}", 0);
        $score -= ($failedAttempts * 5);

        // Check permission escalation attempts
        $escalationAttempts = Cache::get("escalation_attempts_{$userId}", 0);
        $score -= ($escalationAttempts * 20);

        // Check data access patterns
        if ($this->hasAbnormalDataAccess($userId)) {
            $score -= 25;
        }

        // Check time patterns
        if ($this->isUnusualTime($userId)) {
            $score -= 10;
        }

        return (int) max(0, min(100, $score));
    }

    /**
     * Calculate context score (0-100)
     *
     * @param  array<string, mixed>  $context
     */
    private function calculateContextScore(array $context): int
    {
        $score = 50;

        // Business hours +20
        $hour = (int) date('H');
        if ($hour >= 8 && $hour <= 18) {
            $score += 20;
        }

        // Weekday +10
        if (! in_array(date('w'), [0, 6])) {
            $score += 10;
        }

        // Expected geo-location +20
        if ($this->isExpectedLocation($context)) {
            $score += 20;
        }

        // Known user agent +10
        if ($this->isKnownUserAgent($context)) {
            $score += 10;
        }

        // Consistent session +10
        if ($this->hasConsistentSession($context)) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Get required score based on action and resource
     */
    private function getRequiredScore(string $action, string $resource): int
    {
        // Base scores by action
        $actionScores = [
            'read' => 30,
            'write' => 50,
            'update' => 60,
            'delete' => 70,
            'admin' => 80,
            'system' => 90,
        ];

        // Resource sensitivity multipliers
        $resourceMultipliers = [
            'public' => 0.5,
            'internal' => 1.0,
            'confidential' => 1.3,
            'secret' => 1.5,
            'top_secret' => 2.0,
        ];

        $baseScore = $actionScores[$action] ?? 50;
        $multiplier = $resourceMultipliers[$this->getResourceSensitivity($resource)] ?? 1.0;

        return min(100, (int) ($baseScore * $multiplier));
    }

    /**
     * Get recommendations for improving trust score
     *
     * @param  array<string, int>  $scores
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function getRecommendations(array $scores, array $context): array
    {
        $recommendations = [];

        if ($scores['device'] < 50) {
            $recommendations[] = [
                'type' => 'device',
                'action' => 'verify_device',
                'message' => 'Please verify your device through email confirmation',
            ];
        }

        if ($scores['user'] < 50) {
            $recommendations[] = [
                'type' => 'user',
                'action' => 'enable_mfa',
                'message' => 'Enable multi-factor authentication to increase security',
            ];
        }

        if ($scores['network'] < 30) {
            $recommendations[] = [
                'type' => 'network',
                'action' => 'use_vpn',
                'message' => 'Connect from a trusted network or corporate VPN',
            ];
        }

        if ($scores['behavior'] < 50) {
            $recommendations[] = [
                'type' => 'behavior',
                'action' => 'slow_down',
                'message' => 'Unusual activity detected. Please slow down your requests',
            ];
        }

        return $recommendations;
    }

    /**
     * Check if step-up authentication can help
     *
     * @param  array<string, mixed>  $context
     */
    private function canStepUp(array $context, int $currentScore, int $requiredScore): bool
    {
        // If score is within 20 points, step-up auth might help
        return ($requiredScore - $currentScore) <= 20;
    }

    /**
     * Get available step-up methods
     *
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function getStepUpMethods(array $context): array
    {
        $methods = [];

        $user = User::find($context['user_id']);
        if (! $user || $user instanceof \Illuminate\Database\Eloquent\Collection) {
            return [];
        }

        if (isset($user->phone_verified_at) && $user->phone_verified_at) {
            $methods[] = 'sms_code';
        }

        if (isset($user->mfa_enabled) && $user->mfa_enabled) {
            $methods[] = 'totp';
        }

        if (isset($user->email_verified_at) && $user->email_verified_at) {
            $methods[] = 'email_code';
        }

        if ($this->hasBackupCodes($user)) {
            $methods[] = 'backup_code';
        }

        // Always available
        $methods[] = 'security_questions';

        return $methods;
    }

    /**
     * Log Zero Trust decision
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $decision
     */
    private function logDecision(array $context, array $decision): void
    {
        $this->siem->sendSecurityEvent([
            'event_id' => 'ZEROTRUST_DECISION',
            'severity' => $decision['allowed'] ? 'info' : 'warning',
            'action' => $context['action'],
            'user_id' => $context['user_id'],
            'ip' => $context['ip'],
            'message' => sprintf(
                'Zero Trust: %s access to %s (score: %d/%d)',
                $decision['allowed'] ? 'Allowed' : 'Denied',
                $context['resource'],
                $decision['score'],
                $decision['required_score']
            ),
            'metadata' => [
                'scores' => $decision['scores'],
                'context' => $context,
            ],
        ]);

        // Store for analytics
        DB::table('zero_trust_decisions')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'user_id' => $context['user_id'],
            'action' => $context['action'],
            'resource' => $context['resource'],
            'score' => $decision['score'],
            'required_score' => $decision['required_score'],
            'allowed' => $decision['allowed'],
            'scores' => json_encode($decision['scores']),
            'created_at' => now(),
        ]);
    }

    // Helper methods
    private function isOldOSVersion(string $version): bool
    {
        // Check against minimum supported versions
        return false; // Simplified
    }

    private function isKnownLocation(string $ip, string $userId): bool
    {
        return DB::table('user_locations')
            ->where('user_id', $userId)
            ->where('ip_address', $ip)
            ->exists();
    }

    private function hasPrivilegedAccess(User $user): bool
    {
        return $user->groups()
            ->whereIn('slug', ['admin', 'super-admin', 'security'])
            ->exists();
    }

    private function isDormantReactivated(User $user): bool
    {
        return $user->last_login_at &&
               $user->last_login_at < now()->subMonths(3) &&
               $user->last_login_at > now()->subDays(1);
    }

    private function isCorporateNetwork(string $ip): bool
    {
        $corporateRanges = config('security.corporate_ip_ranges', []);
        foreach ($corporateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function isKnownUserIP(string $ip, string $userId): bool
    {
        return Cache::remember("known_ip_{$userId}_{$ip}", 3600, function () use ($ip, $userId) {
            return DB::table('user_sessions')
                ->where('user_id', $userId)
                ->where('ip_address', $ip)
                ->where('created_at', '>', now()->subDays(30))
                ->exists();
        });
    }

    private function isResidentialISP(string $ip): bool
    {
        // Check against ISP database
        return true; // Simplified
    }

    private function isKnownVPN(string $ip): bool
    {
        // Check against VPN provider ranges
        return false; // Simplified
    }

    private function isMobileCarrier(string $ip): bool
    {
        // Check against mobile carrier ranges
        return false; // Simplified
    }

    private function isPublicWiFi(string $ip): bool
    {
        // Check against known public WiFi ranges
        return false; // Simplified
    }

    private function isTorExitNode(string $ip): bool
    {
        return Cache::remember("tor_check_{$ip}", 3600, function () {
            // Check against Tor exit node list
            return false; // Simplified
        });
    }

    private function isBlacklisted(string $ip): bool
    {
        return Cache::remember("blacklist_{$ip}", 3600, function () use ($ip) {
            return DB::table('ip_blacklist')
                ->where('ip_address', $ip)
                ->where('expires_at', '>', now())
                ->exists();
        });
    }

    private function hasAbnormalDataAccess(string $userId): bool
    {
        // Check for unusual data access patterns
        $recentAccess = DB::table('data_access_logs')
            ->where('user_id', $userId)
            ->where('created_at', '>', now()->subHour())
            ->count();

        return $recentAccess > 100; // Threshold
    }

    private function isUnusualTime(string $userId): bool
    {
        $hour = (int) date('H');
        $normalHours = Cache::get("user_normal_hours_{$userId}", range(8, 18));

        return ! in_array($hour, $normalHours);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isExpectedLocation(array $context): bool
    {
        // Check if location matches user's usual locations
        return true; // Simplified
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isKnownUserAgent(array $context): bool
    {
        return Cache::remember("ua_{$context['user_id']}_{$context['user_agent']}", 86400, function () use ($context) {
            return DB::table('user_devices')
                ->where('user_id', $context['user_id'])
                ->where('user_agent', $context['user_agent'])
                ->exists();
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function hasConsistentSession(array $context): bool
    {
        // Check session consistency
        return isset($context['session_id']) &&
               Cache::has("session_{$context['session_id']}");
    }

    private function getResourceSensitivity(string $resource): string
    {
        // Classify resource sensitivity
        if (strpos($resource, '/public/') !== false) {
            return 'public';
        }
        if (strpos($resource, '/api/') !== false) {
            return 'internal';
        }
        if (strpos($resource, '/admin/') !== false) {
            return 'confidential';
        }
        if (strpos($resource, '/financial/') !== false) {
            return 'secret';
        }
        if (strpos($resource, '/security/') !== false) {
            return 'top_secret';
        }

        return 'internal';
    }

    private function hasBackupCodes(User $user): bool
    {
        return ! empty($user->mfa_recovery_codes);
    }

    private function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $mask] = explode('/', $range);
        $subnet = ip2long($subnet);
        $ip = ip2long($ip);
        $mask = -1 << (32 - (int) $mask);
        $subnet &= $mask;

        return ($ip & $mask) == $subnet;
    }
}
