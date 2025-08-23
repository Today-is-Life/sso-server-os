<?php

declare(strict_types=1);

namespace App\Services\SIEM;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SIEMService
{
    /** @var array<string, mixed> */
    private array $config;

    private string $provider;

    public function __construct()
    {
        $this->config = config('siem', []);
        $this->provider = $this->config['provider'] ?? 'syslog';
    }

    /**
     * Send security event to SIEM and broadcast to domains
     *
     * @param  array<string, mixed>  $event
     */
    public function sendSecurityEvent(array $event): void
    {
        // Add server context
        $event['server'] = 'sso_master';
        $event['server_id'] = config('app.server_id', 'sso-001');

        // Send to SIEM
        $formattedEvent = $this->formatEvent($event);
        $this->sendToProvider($formattedEvent);

        // Broadcast critical events to all domains
        if ($this->isCriticalEvent($event)) {
            $this->broadcastToDomains($event);
        }

        // Store for correlation
        $this->storeForCorrelation($event);
    }

    /**
     * Format event in CEF (Common Event Format)
     *
     * @param  array<string, mixed>  $event
     */
    private function formatEvent(array $event): string
    {
        $cef = sprintf(
            'CEF:0|TIL42|SSO-Server|2.0|%s|%s|%d|',
            $event['event_id'] ?? 'UNKNOWN',
            $event['name'] ?? 'Security Event',
            $this->getSeverity($event['severity'] ?? 'info')
        );

        // Add extension fields
        $extensions = [];
        $extensions[] = 'dvc='.gethostname();
        $extensions[] = 'dvctime='.time();
        $extensions[] = 'src='.($event['ip'] ?? request()->ip());
        $extensions[] = 'suser='.($event['user_id'] ?? 'anonymous');
        $extensions[] = 'act='.($event['action'] ?? 'unknown');
        $extensions[] = 'msg='.($event['message'] ?? '');
        $extensions[] = 'cs1Label=Server';
        $extensions[] = 'cs1='.$event['server_id'];

        // Add domain correlation
        if (isset($event['domain_id'])) {
            $extensions[] = 'cs2Label=Domain';
            $extensions[] = 'cs2='.$event['domain_id'];
        }

        // Add correlation ID for tracking across systems
        $extensions[] = 'cs3Label=CorrelationID';
        $extensions[] = 'cs3='.($event['correlation_id'] ?? \Illuminate\Support\Str::uuid());

        if (isset($event['metadata'])) {
            $extensions[] = 'cs4Label=Metadata';
            $extensions[] = 'cs4='.json_encode($event['metadata']);
        }

        return $cef.implode(' ', $extensions);
    }

    /**
     * Send to configured provider
     */
    private function sendToProvider(string $event): void
    {
        switch ($this->provider) {
            case 'splunk':
                $this->sendToSplunk($event);
                break;
            case 'elasticsearch':
                $this->sendToElastic($event);
                break;
            case 'datadog':
                $this->sendToDatadog($event);
                break;
            case 'syslog':
            default:
                $this->sendToSyslog($event);
                break;
        }
    }

    /**
     * Broadcast critical events to all registered domains
     *
     * @param  array<string, mixed>  $event
     */
    private function broadcastToDomains(array $event): void
    {
        $domains = \App\Models\Domain::where('is_active', true)->get();

        foreach ($domains as $domain) {
            try {
                // Sign the event
                $signature = hash_hmac('sha256', json_encode($event), $domain->webhook_secret);

                Http::withHeaders([
                    'X-SSO-Event' => $event['event_id'],
                    'X-SSO-Signature' => $signature,
                    'X-SSO-Timestamp' => time(),
                ])->post($domain->url.'/sso/security-event', $event);

            } catch (\Exception $e) {
                Log::error('Failed to broadcast security event to domain', [
                    'domain' => $domain->id,
                    'event' => $event['event_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Store event for correlation and analysis
     *
     * @param  array<string, mixed>  $event
     */
    private function storeForCorrelation(array $event): void
    {
        // Store in cache for quick correlation (1 hour)
        $key = 'security_event_'.($event['correlation_id'] ?? \Illuminate\Support\Str::uuid());
        Cache::put($key, $event, 3600);

        // Store in database for long-term analysis
        \DB::table('security_events')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'event_id' => $event['event_id'],
            'severity' => $event['severity'],
            'user_id' => $event['user_id'] ?? null,
            'domain_id' => $event['domain_id'] ?? null,
            'ip_address' => $event['ip'] ?? null,
            'action' => $event['action'],
            'message' => $event['message'],
            'metadata' => json_encode($event['metadata'] ?? []),
            'correlation_id' => $event['correlation_id'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Check if event is critical and needs immediate broadcast
     *
     * @param  array<string, mixed>  $event
     */
    private function isCriticalEvent(array $event): bool
    {
        $criticalEvents = [
            'AUTH_SUSPICIOUS_LOGIN',
            'ANOMALY_IMPOSSIBLE_TRAVEL',
            'ANOMALY_BRUTE_FORCE',
            'PERM_PRIVILEGE_ESCALATION',
            'DATA_BREACH_ATTEMPT',
            'SYSTEM_COMPROMISE',
        ];

        return in_array($event['event_id'] ?? '', $criticalEvents) ||
               ($event['severity'] ?? 'info') === 'critical';
    }

    /**
     * Send to Splunk HEC
     */
    private function sendToSplunk(string $event): void
    {
        if (! isset($this->config['splunk'])) {
            $this->sendToSyslog($event);

            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Splunk '.$this->config['splunk']['token'],
            ])->post($this->config['splunk']['url'], [
                'time' => time(),
                'host' => gethostname(),
                'source' => 'sso_server',
                'sourcetype' => 'cef',
                'index' => 'security',
                'event' => $event,
            ]);

            if (! $response->successful()) {
                Log::error('Splunk HEC failed', ['status' => $response->status()]);
                $this->sendToSyslog($event);
            }
        } catch (\Exception $e) {
            Log::error('Splunk error: '.$e->getMessage());
            $this->sendToSyslog($event);
        }
    }

    /**
     * Send to Elasticsearch
     */
    private function sendToElastic(string $event): void
    {
        if (! isset($this->config['elasticsearch'])) {
            $this->sendToSyslog($event);

            return;
        }

        try {
            $response = Http::withBasicAuth(
                $this->config['elasticsearch']['username'],
                $this->config['elasticsearch']['password']
            )->post(
                $this->config['elasticsearch']['url'].'/sso-security-'.date('Y.m.d').'/_doc',
                [
                    '@timestamp' => now()->toIso8601String(),
                    'event' => $event,
                    'type' => 'security',
                    'source' => 'sso_server',
                ]
            );

            if (! $response->successful()) {
                Log::error('Elasticsearch failed', ['status' => $response->status()]);
                $this->sendToSyslog($event);
            }
        } catch (\Exception $e) {
            Log::error('Elasticsearch error: '.$e->getMessage());
            $this->sendToSyslog($event);
        }
    }

    /**
     * Send to Datadog
     */
    private function sendToDatadog(string $event): void
    {
        if (! isset($this->config['datadog'])) {
            $this->sendToSyslog($event);

            return;
        }

        try {
            $response = Http::withHeaders([
                'DD-API-KEY' => $this->config['datadog']['api_key'],
                'Content-Type' => 'application/json',
            ])->post('https://http-intake.logs.datadoghq.com/v1/input', [
                'ddsource' => 'sso_server',
                'ddtags' => 'env:'.config('app.env'),
                'hostname' => gethostname(),
                'service' => 'sso',
                'message' => $event,
            ]);

            if (! $response->successful()) {
                Log::error('Datadog failed', ['status' => $response->status()]);
                $this->sendToSyslog($event);
            }
        } catch (\Exception $e) {
            Log::error('Datadog error: '.$e->getMessage());
            $this->sendToSyslog($event);
        }
    }

    /**
     * Send to syslog
     */
    private function sendToSyslog(string $event): void
    {
        $priority = LOG_AUTH | LOG_INFO;
        openlog('sso_server', LOG_PID | LOG_PERROR, LOG_AUTH);
        syslog($priority, $event);
        closelog();

        Log::channel('security')->info($event);
    }

    /**
     * Convert severity to CEF numeric
     */
    private function getSeverity(string $severity): int
    {
        $map = [
            'debug' => 0,
            'info' => 3,
            'warning' => 6,
            'error' => 8,
            'critical' => 10,
        ];

        return $map[$severity] ?? 5;
    }

    /**
     * Correlate events across domains
     *
     * @return array<int, mixed>
     */
    public function correlateEvents(string $correlationId): array
    {
        return \DB::table('security_events')
            ->where('correlation_id', $correlationId)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Get security metrics for dashboard
     *
     * @return array<string, mixed>
     */
    public function getSecurityMetrics(): array
    {
        $last24h = now()->subHours(24);

        return [
            'total_events' => \DB::table('security_events')
                ->where('created_at', '>', $last24h)
                ->count(),
            'critical_events' => \DB::table('security_events')
                ->where('created_at', '>', $last24h)
                ->where('severity', 'critical')
                ->count(),
            'unique_users' => \DB::table('security_events')
                ->where('created_at', '>', $last24h)
                ->distinct('user_id')
                ->count('user_id'),
            'top_events' => \DB::table('security_events')
                ->where('created_at', '>', $last24h)
                ->select('event_id', \DB::raw('count(*) as count'))
                ->groupBy('event_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];
    }
}
