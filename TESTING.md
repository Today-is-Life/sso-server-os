# 🔒 SSO Server Security Testing Guide

## Overview

This guide covers comprehensive testing strategies for the SSO Server's enterprise security features.

## Test Architecture

```
┌─────────────────────────────────────────┐
│         Automated Test Suite            │
├─────────────────────────────────────────┤
│  • Unit Tests (PHPUnit)                 │
│  • Integration Tests                    │
│  • Security Tests                       │
│  • Performance Tests                    │
│  • E2E Tests                           │
└─────────────────────────────────────────┘
```

## 🚀 Quick Start

### 1. Run Quick Security Check
```bash
php artisan security:test --quick
```

### 2. Run Full Security Suite
```bash
php artisan security:test --full --report
```

### 3. Run Specific Test Suites
```bash
# Anomaly Detection Tests
php artisan test --filter=AnomalyDetection

# Zero Trust Tests
php artisan test --filter=ZeroTrust

# GDPR Compliance Tests
php artisan test --filter=GDPR
```

## 📊 Test Categories

### 1. Security Configuration Tests
- ✅ RSA Key presence
- ✅ HTTPS enforcement
- ✅ CORS configuration
- ✅ Rate limiting
- ✅ Debug mode check

### 2. Authentication Security
- ✅ Password complexity
- ✅ MFA implementation
- ✅ Session security
- ✅ JWT expiration

### 3. Anomaly Detection
- ✅ Impossible travel detection
- ✅ Unusual login times
- ✅ New device detection
- ✅ Concurrent sessions
- ✅ Brute force detection
- ✅ Tor/Proxy detection

### 4. Zero Trust Architecture
- ✅ Trust score calculation
- ✅ Device trust scoring
- ✅ User risk scoring
- ✅ Network trust scoring
- ✅ Behavior scoring
- ✅ Context scoring
- ✅ Step-up authentication

### 5. GDPR Compliance
- ✅ Article 15: Data Export
- ✅ Article 17: Right to Erasure
- ✅ Article 18: Processing Restriction
- ✅ Article 20: Data Portability
- ✅ Article 21: Right to Object

### 6. SIEM Integration
- ✅ Event formatting (CEF)
- ✅ Provider support (Splunk, ELK, Datadog)
- ✅ Critical event broadcasting
- ✅ Event correlation

## 🐳 Docker Test Environment

### Setup
```bash
# Start test environment
docker-compose -f docker-compose.test.yml up -d

# Run tests in container
docker-compose -f docker-compose.test.yml exec sso-test php artisan test

# Run security scan
docker-compose -f docker-compose.test.yml up security-scanner
```

### Services
- **sso-test**: Main application container
- **mysql-test**: Test database (port 3307)
- **redis-test**: Test cache (port 6380)
- **siem-simulator**: Mock SIEM endpoint
- **security-scanner**: OWASP ZAP scanner

## 🔧 Manual Testing

### 1. Test Impossible Travel
```php
// Simulate login from New York
Cache::put("last_login_location_user123", [
    'ip' => '1.2.3.4',
    'location' => ['lat' => 40.7128, 'lon' => -74.0060],
    'timestamp' => time() - 1800,
]);

// Try login from Tokyo (should trigger alert)
$service->detectLoginAnomalies($user, ['ip' => '5.6.7.8']);
```

### 2. Test Zero Trust
```php
$context = [
    'user_id' => $user->id,
    'device_id' => null, // Unknown device
    'ip' => '1.2.3.4',
    'action' => 'delete',
    'resource' => '/api/admin/users'
];

$decision = $zeroTrust->verifyRequest($context);
// Should deny due to low trust score
```

### 3. Test GDPR Export
```php
$gdpr = app(GDPRService::class);
$filename = $gdpr->exportUserData($user);
// Check encrypted export in storage/app/gdpr-exports/
```

## 📈 Performance Benchmarks

| Test | Target | Actual |
|------|--------|--------|
| Anomaly Detection | < 100ms | ~45ms |
| Zero Trust Decision | < 50ms | ~20ms |
| GDPR Export | < 5s | ~2s |
| SIEM Event | < 10ms | ~3ms |

## 🔍 Security Checklist

### Pre-Production
- [ ] Run full security test suite
- [ ] PHPStan Level 7 (< 50 errors)
- [ ] PSR-12 compliance
- [ ] Dependency audit
- [ ] OWASP scan
- [ ] Load testing
- [ ] Penetration testing

### Production Monitoring
- [ ] SIEM alerts configured
- [ ] Anomaly detection active
- [ ] Zero Trust enforced
- [ ] Rate limiting enabled
- [ ] Audit logs active
- [ ] Backup procedures tested

## 🚨 Common Issues

### Issue: Impossible travel not detected
**Solution**: Check GeoIP service configuration and Cache driver

### Issue: Zero Trust blocking legitimate users
**Solution**: Adjust trust score thresholds in config/security.php

### Issue: GDPR export failing
**Solution**: Check storage permissions and encryption keys

### Issue: SIEM events not sending
**Solution**: Verify provider configuration in config/siem.php

## 📝 Test Reports

Reports are generated in:
- **HTML Report**: `storage/app/security-report-*.html`
- **PHPUnit Coverage**: `storage/test-reports/coverage/`
- **OWASP Report**: `storage/test-reports/owasp/`
- **Performance Report**: `storage/test-reports/performance/`

## 🔄 CI/CD Integration

### GitHub Actions
```yaml
- name: Run Security Tests
  run: php artisan security:test --full
  
- name: Check Security Score
  run: |
    SCORE=$(php artisan security:test --quick | grep "Results:")
    if [ "$SCORE" -lt "80" ]; then
      exit 1
    fi
```

### GitLab CI
```yaml
security_test:
  script:
    - php artisan security:test --full --report
  artifacts:
    paths:
      - storage/app/security-report-*.html
```

## 📊 Metrics

### Security Score Calculation
```
Score = (Passed Tests / Total Tests) * 100

Grades:
A+ : 95-100%
A  : 90-94%
B+ : 85-89%
B  : 80-84%
C+ : 75-79%
C  : 70-74%
D  : 60-69%
F  : < 60%
```

## 🛠️ Advanced Testing

### Load Testing with Anomaly Detection
```bash
# Using Apache Bench
ab -n 1000 -c 10 -H "X-User-Agent: LoadTest" http://localhost/api/login

# Monitor anomaly detection
tail -f storage/logs/security.log | grep ANOMALY
```

### Penetration Testing
```bash
# Using OWASP ZAP
docker run -t owasp/zap2docker-stable zap-baseline.py \
  -t http://your-sso-server.com \
  -r penetration-report.html
```

## 📚 References

- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [GDPR Compliance Testing](https://gdpr.eu/compliance/)
- [Zero Trust Architecture](https://www.nist.gov/publications/zero-trust-architecture)
- [SIEM Best Practices](https://www.sans.org/white-papers/)

## 💡 Tips

1. **Always test in isolation**: Use separate test database
2. **Mock external services**: Don't hit real APIs in tests
3. **Test edge cases**: Impossible travel, concurrent logins, etc.
4. **Automate everything**: CI/CD should run all tests
5. **Monitor production**: Use SIEM alerts for real-time monitoring

---

**Last Updated**: 2024-08-23
**Version**: 2.0
**Maintained by**: SSO Security Team