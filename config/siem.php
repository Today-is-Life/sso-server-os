<?php

return [
    /**
     * SIEM Provider
     * Options: syslog, splunk, elasticsearch, datadog
     */
    'provider' => env('SIEM_PROVIDER', 'syslog'),
    
    /**
     * Splunk Configuration
     */
    'splunk' => [
        'url' => env('SPLUNK_HEC_URL'),
        'token' => env('SPLUNK_HEC_TOKEN'),
        'index' => env('SPLUNK_INDEX', 'security'),
        'verify_ssl' => env('SPLUNK_VERIFY_SSL', true),
    ],
    
    /**
     * Elasticsearch Configuration
     */
    'elasticsearch' => [
        'url' => env('ELASTIC_URL', 'http://localhost:9200'),
        'username' => env('ELASTIC_USERNAME'),
        'password' => env('ELASTIC_PASSWORD'),
        'index_prefix' => env('ELASTIC_INDEX_PREFIX', 'sso-security'),
    ],
    
    /**
     * Datadog Configuration
     */
    'datadog' => [
        'api_key' => env('DATADOG_API_KEY'),
        'region' => env('DATADOG_REGION', 'us'), // us, eu
        'tags' => [
            'service' => 'sso-server',
            'env' => env('APP_ENV', 'production'),
        ],
    ],
    
    /**
     * Event Correlation
     */
    'correlation' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'max_events' => 1000,
    ],
    
    /**
     * Critical Events (always broadcast to domains)
     */
    'critical_events' => [
        'AUTH_SUSPICIOUS_LOGIN',
        'ANOMALY_IMPOSSIBLE_TRAVEL',
        'ANOMALY_BRUTE_FORCE',
        'PERM_PRIVILEGE_ESCALATION',
        'DATA_BREACH_ATTEMPT',
        'SYSTEM_COMPROMISE',
        'ACCOUNT_TAKEOVER',
        'MFA_BYPASS_ATTEMPT',
    ],
    
    /**
     * Event Retention (days)
     */
    'retention' => [
        'critical' => 365,  // 1 year
        'error' => 180,     // 6 months
        'warning' => 90,    // 3 months
        'info' => 30,       // 1 month
        'debug' => 7,       // 1 week
    ],
    
    /**
     * Real-time Alerting
     */
    'alerting' => [
        'enabled' => env('SIEM_ALERTING_ENABLED', true),
        'channels' => [
            'email' => env('SIEM_ALERT_EMAIL'),
            'slack' => env('SIEM_ALERT_SLACK_WEBHOOK'),
            'pagerduty' => env('SIEM_ALERT_PAGERDUTY_KEY'),
        ],
        'thresholds' => [
            'failed_logins' => 10,      // per user per hour
            'new_devices' => 5,         // per user per day
            'permission_denials' => 20, // per user per hour
        ],
    ],
    
    /**
     * Metrics Collection
     */
    'metrics' => [
        'enabled' => true,
        'interval' => 60, // seconds
        'exporters' => [
            'prometheus' => env('PROMETHEUS_ENABLED', false),
            'statsd' => env('STATSD_ENABLED', false),
        ],
    ],
];