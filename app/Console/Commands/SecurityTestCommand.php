<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tests\SecurityTestSuite;

class SecurityTestCommand extends Command
{
    protected $signature = 'security:test 
                            {--full : Run full security test suite}
                            {--quick : Run quick security checks only}
                            {--report : Generate detailed HTML report}';

    protected $description = 'Run comprehensive security tests for SSO server';

    public function handle(): int
    {
        $this->info('🔒 SSO Security Test Suite v2.0');
        $this->newLine();

        if ($this->option('quick')) {
            return $this->runQuickTests();
        }

        $suite = new SecurityTestSuite();
        $results = $suite->run();

        if ($this->option('report')) {
            $this->generateHtmlReport($results);
        }

        // Return exit code based on failures
        return $results['failed'] > 0 ? 1 : 0;
    }

    private function runQuickTests(): int
    {
        $this->info('Running quick security checks...');
        
        $checks = [
            'HTTPS Enabled' => config('app.force_https', false),
            'Debug Mode Off' => !config('app.debug'),
            'MFA Available' => class_exists(\App\Services\Security\MFAService::class),
            'Rate Limiting' => config('app.rate_limit', 60) <= 100,
            'CORS Configured' => !in_array('*', config('cors.allowed_origins', ['*'])),
        ];

        $passed = 0;
        $failed = 0;

        foreach ($checks as $check => $result) {
            if ($result) {
                $this->info("✅ {$check}");
                $passed++;
            } else {
                $this->error("❌ {$check}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Results: {$passed} passed, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }

    private function generateHtmlReport(array $results): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>SSO Security Test Report</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .score-card {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .score-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .score-item h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        .score-item .value {
            font-size: 36px;
            font-weight: bold;
        }
        .passed { color: #10b981; }
        .warning { color: #f59e0b; }
        .failed { color: #ef4444; }
        .grade {
            font-size: 72px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .test-results {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-item {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .test-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-passed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-warning {
            background: #fed7aa;
            color: #92400e;
        }
        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔒 SSO Security Test Report</h1>
        <p>Generated: {date}</p>
    </div>

    <div class="score-card">
        <div class="score-item">
            <h3>Security Score</h3>
            <div class="value">{score}%</div>
        </div>
        <div class="score-item">
            <h3>Grade</h3>
            <div class="value grade">{grade}</div>
        </div>
        <div class="score-item">
            <h3>Passed</h3>
            <div class="value passed">{passed}</div>
        </div>
        <div class="score-item">
            <h3>Warnings</h3>
            <div class="value warning">{warnings}</div>
        </div>
        <div class="score-item">
            <h3>Failed</h3>
            <div class="value failed">{failed}</div>
        </div>
    </div>

    <div class="test-results">
        <h2>Test Results</h2>
        {test_results}
    </div>
</body>
</html>
HTML;

        $testResultsHtml = '';
        foreach ($results['results'] as $test) {
            $status = $test['status'];
            $badge = ucfirst($status);
            $testResultsHtml .= <<<ITEM
<div class="test-item">
    <span>{$test['test']}</span>
    <span class="status-badge status-{$status}">{$badge}</span>
</div>
ITEM;
        }

        $html = str_replace('{date}', now()->format('Y-m-d H:i:s'), $html);
        $html = str_replace('{score}', $results['score'], $html);
        $html = str_replace('{grade}', $results['grade'], $html);
        $html = str_replace('{passed}', $results['passed'], $html);
        $html = str_replace('{warnings}', $results['warnings'], $html);
        $html = str_replace('{failed}', $results['failed'], $html);
        $html = str_replace('{test_results}', $testResultsHtml, $html);

        $filename = storage_path('app/security-report-' . now()->format('Y-m-d-His') . '.html');
        file_put_contents($filename, $html);

        $this->info("📊 HTML report generated: {$filename}");
    }
}