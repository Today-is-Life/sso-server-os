# SSO Server API Documentation

## Base URL
```
https://sso.yourdomain.com/api/v1
```

## Authentication
Most endpoints require authentication via Bearer token in the Authorization header:
```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

---

## Authentication Endpoints

### 1. Register User
**POST** `/auth/register`

Creates a new user account.

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Registration successful. Please check your email for verification.",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

### 2. Login
**POST** `/auth/login`

Authenticates a user and returns access tokens.

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "remember": true
}
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified": true,
    "mfa_enabled": false
  },
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### 3. Logout
**POST** `/auth/logout`

Invalidates the current session.

**Headers Required:** `Authorization: Bearer TOKEN`

**Response (200):**
```json
{
  "success": true,
  "message": "Successfully logged out"
}
```

### 4. Verify Email
**GET** `/auth/verify-email/{token}`

Verifies a user's email address.

**Response (200):**
```json
{
  "success": true,
  "message": "Email verified successfully"
}
```

---

## Magic Link Authentication

### 1. Request Magic Link
**POST** `/auth/magic-link`

Sends a passwordless login link via email.

**Request Body:**
```json
{
  "email": "john@example.com"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Magic link sent to your email"
}
```

### 2. Verify Magic Link
**GET** `/auth/magic/{token}`

Authenticates user via magic link token.

**Response (200):**
```json
{
  "success": true,
  "user": {...},
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

## Two-Factor Authentication (2FA)

### 1. Enable 2FA
**POST** `/2fa/enable`

Generates a TOTP secret for 2FA.

**Headers Required:** `Authorization: Bearer TOKEN`

**Response (200):**
```json
{
  "success": true,
  "secret": "JBSWY3DPEHPK3PXP",
  "qr_code": "data:image/png;base64,...",
  "recovery_codes": [
    "AAAA-BBBB-CCCC",
    "DDDD-EEEE-FFFF",
    "GGGG-HHHH-IIII"
  ]
}
```

### 2. Confirm 2FA
**POST** `/2fa/confirm`

Confirms and activates 2FA.

**Headers Required:** `Authorization: Bearer TOKEN`

**Request Body:**
```json
{
  "token": "123456"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "2FA enabled successfully"
}
```

### 3. Verify 2FA
**POST** `/2fa/verify`

Verifies a TOTP token during login.

**Request Body:**
```json
{
  "email": "john@example.com",
  "token": "123456"
}
```

**Response (200):**
```json
{
  "success": true,
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### 4. Disable 2FA
**POST** `/2fa/disable`

Disables 2FA for the authenticated user.

**Headers Required:** `Authorization: Bearer TOKEN`

**Request Body:**
```json
{
  "password": "CurrentPassword123!"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "2FA disabled successfully"
}
```

---

## Social Login

### 1. Redirect to Provider
**GET** `/auth/social/{provider}/redirect`

Redirects to OAuth provider for authentication.

**Supported Providers:**
- google
- github
- facebook
- instagram
- linkedin
- twitter
- microsoft
- apple

### 2. Provider Callback
**GET** `/auth/social/{provider}/callback`

Handles OAuth callback from provider.

---

## OAuth2/OIDC Endpoints

### 1. Authorization
**GET** `/oauth/authorize`

OAuth2 authorization endpoint.

**Query Parameters:**
- `client_id` (required): Your application's client ID
- `redirect_uri` (required): Callback URL
- `response_type` (required): "code" or "token"
- `scope` (optional): Space-separated scopes
- `state` (optional): CSRF protection
- `code_challenge` (optional): PKCE challenge
- `code_challenge_method` (optional): "S256"

### 2. Token Exchange
**POST** `/oauth/token`

Exchanges authorization code for access token.

**Request Body:**
```json
{
  "grant_type": "authorization_code",
  "client_id": "your-client-id",
  "client_secret": "your-client-secret",
  "redirect_uri": "https://yourapp.com/callback",
  "code": "authorization-code",
  "code_verifier": "pkce-verifier"
}
```

**Response (200):**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "def50200...",
  "scope": "read write"
}
```

### 3. User Info
**GET** `/oauth/userinfo`

Returns authenticated user information.

**Headers Required:** `Authorization: Bearer TOKEN`

**Response (200):**
```json
{
  "sub": "550e8400-e29b-41d4-a716-446655440000",
  "name": "John Doe",
  "email": "john@example.com",
  "email_verified": true,
  "picture": "https://example.com/avatar.jpg",
  "locale": "en",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

---

## User Management

### 1. Get Current User
**GET** `/user`

Returns current authenticated user.

**Headers Required:** `Authorization: Bearer TOKEN`

**Response (200):**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "name": "John Doe",
  "email": "john@example.com",
  "email_verified_at": "2024-01-01T00:00:00Z",
  "mfa_enabled": false,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### 2. Update Profile
**PUT** `/user/profile`

Updates user profile information.

**Headers Required:** `Authorization: Bearer TOKEN`

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Smith",
  "timezone": "America/New_York",
  "locale": "en_US"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "user": {...}
}
```

### 3. Change Password
**POST** `/user/password`

Changes user password.

**Headers Required:** `Authorization: Bearer TOKEN`

**Request Body:**
```json
{
  "current_password": "OldPassword123!",
  "new_password": "NewPassword456!",
  "new_password_confirmation": "NewPassword456!"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

---

## Password Reset

### 1. Request Reset
**POST** `/password/email`

Sends password reset link via email.

**Request Body:**
```json
{
  "email": "john@example.com"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Password reset link sent to your email"
}
```

### 2. Reset Password
**POST** `/password/reset`

Resets password using token.

**Request Body:**
```json
{
  "token": "reset-token",
  "email": "john@example.com",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Password reset successfully"
}
```

---

## Rate Limiting

All API endpoints are rate limited:

| Endpoint Type | Limit per IP | Limit per User |
|--------------|--------------|----------------|
| Login/Register | 10/min | 5/min |
| Magic Link | 3/min | 2/min |
| 2FA Operations | 20/min | 10/min |
| OAuth | 30/min | 20/min |
| General API | 100/min | 60/min |

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Request limit
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Reset timestamp

---

## Error Responses

### 400 Bad Request
```json
{
  "error": "Validation failed",
  "message": "The given data was invalid",
  "errors": {
    "email": ["The email field is required"]
  }
}
```

### 401 Unauthorized
```json
{
  "error": "Unauthorized",
  "message": "Invalid or expired token"
}
```

### 403 Forbidden
```json
{
  "error": "Forbidden",
  "message": "You don't have permission to access this resource"
}
```

### 404 Not Found
```json
{
  "error": "Not found",
  "message": "Resource not found"
}
```

### 429 Too Many Requests
```json
{
  "error": "Rate limit exceeded",
  "message": "Too many requests from your IP address",
  "retry_after": 60
}
```

### 500 Internal Server Error
```json
{
  "error": "Server error",
  "message": "An unexpected error occurred"
}
```

---

## Security Headers

All responses include security headers:
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection: 1; mode=block`
- `Content-Security-Policy: default-src 'self'`
- `Strict-Transport-Security: max-age=31536000`

---

## CORS Configuration

For development:
```
Access-Control-Allow-Origin: http://localhost:3000
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
Access-Control-Allow-Credentials: true
```

For production, configure allowed origins in `.env`:
```
CORS_ALLOWED_ORIGINS=https://app.yourdomain.com
```