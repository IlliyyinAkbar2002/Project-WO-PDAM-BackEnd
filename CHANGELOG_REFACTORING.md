# Changelog - API Refactoring to v1

## Version 1.0.0 - November 1, 2025

### 🎯 Summary
Complete refactoring of API structure to support multi-client architecture with versioning, token abilities, and client-specific access control.

---

## ✨ New Features

### 1. API Versioning
- **Added** `/v1/` prefix to all routes
- **Prepared** infrastructure for future API versions (v2, v3, etc.)
- **Structure** now follows: `/api/{version}/{client|resource}/{action}`

### 2. Client-Specific Authentication

#### Mobile Client
- **Added** `POST /api/v1/mobile/login` - Mobile-specific login
- **Added** `POST /api/v1/mobile/register` - Mobile-specific registration  
- **Added** `POST /api/v1/mobile/logout` - Mobile-specific logout
- **Added** `GET /api/v1/mobile/me` - Get authenticated mobile user
- **Token Abilities**: `mobile:access`, `workorder:read`, `workorder:write`

#### Web Client  
- **Added** `POST /api/v1/web/login` - Web-specific login
- **Added** `POST /api/v1/web/logout` - Web-specific logout
- **Added** `GET /api/v1/web/me` - Get authenticated web user
- **Token Abilities**: `web:access`, `workorder:read`, `workorder:write`, `admin:access`

### 3. Token Abilities System
- **Implemented** Laravel Sanctum token abilities
- **Added** fine-grained access control
- **Enabled** client differentiation after authentication
- **Secured** client-specific endpoints

### 4. Client Middleware
- **Created** `app/Http/Middleware/EnsureMobileClient.php`
  - Validates `mobile:access` token ability
  - Returns 403 if web token is used on mobile endpoints
  
- **Created** `app/Http/Middleware/EnsureWebClient.php`
  - Validates `web:access` token ability
  - Returns 403 if mobile token is used on web endpoints

### 5. Enhanced Controllers

#### AuthController
- **Added** `mobileLogin()` method
- **Added** `mobileRegister()` method  
- **Added** `mobileLogout()` method
- **Added** `webLogin()` method
- **Added** `webLogout()` method

#### ProgressWorkorderController
- **Added** `manualRun()` method
- **Moved** manual progress logic from route closure to controller
- **Added** proper error handling and response structure

---

## 🔄 Changed

### Routes (`routes/api.php`)
**Before:**
```php
Route::prefix('mobile')->group(function () {
    Route::post('login', [AuthController::class, 'apiLogin']);
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('workorder', [WorkorderController::class, 'index']);
    // ... other routes
});
```

**After:**
```php
Route::prefix('v1')->group(function () {
    Route::prefix('mobile')->group(function () {
        Route::post('login', [AuthController::class, 'mobileLogin']);
        Route::post('register', [AuthController::class, 'mobileRegister']);
        
        Route::middleware(['auth:sanctum', 'client.mobile'])->group(function () {
            Route::post('logout', [AuthController::class, 'mobileLogout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('workorder', WorkorderController::class);
        // ... other routes
    });
});
```

### Endpoint URLs
All endpoints now include `/v1/` prefix:

| Old Endpoint | New Endpoint | Status |
|--------------|--------------|--------|
| `/api/mobile/login` | `/api/v1/mobile/login` | ⚠️ Breaking Change |
| `/api/mobile/register` | `/api/v1/mobile/register` | ⚠️ Breaking Change |
| `/api/mobile/logout` | `/api/v1/mobile/logout` | ⚠️ Breaking Change |
| `/api/mobile/me` | `/api/v1/mobile/me` | ⚠️ Breaking Change |
| `/api/workorder` | `/api/v1/workorder` | ⚠️ Breaking Change |
| `/api/kpi` | `/api/v1/kpi` | ⚠️ Breaking Change |
| `/api/pegawai` | `/api/v1/pegawai` | ⚠️ Breaking Change |

### Health Check Endpoint
**Enhanced** `/api/ping` response:
```json
{
    "message": "API Laravel Connected!",
    "version": "v1",
    "timestamp": "2025-11-01T03:52:07.000000Z"
}
```

---

## 🗑️ Removed

### Deprecated Methods
- **Removed** `AuthController::apiLogin()` - Replaced by `mobileLogin()` and `webLogin()`
- **Removed** `AuthController::apiLogout()` - Replaced by `mobileLogout()` and `webLogout()`

### Legacy Routes
- **Removed** unversioned mobile auth routes
- **Removed** route closure for progress manual run

---

## 📝 Modified Files

### New Files
```
app/Http/Middleware/EnsureMobileClient.php       ← New
app/Http/Middleware/EnsureWebClient.php          ← New
docs/API_REFACTORING_GUIDE.md                    ← New
docs/API_QUICK_REFERENCE.md                      ← New
docs/Postman_Collection.json                     ← New
README_REFACTORING.md                            ← New
CHANGELOG_REFACTORING.md                         ← New (this file)
```

### Modified Files
```
routes/api.php                                   ← Complete restructure
app/Http/Controllers/AuthController.php          ← Added new methods
app/Http/Controllers/ProgressWorkorderController.php  ← Added manualRun()
app/Http/Kernel.php                              ← Registered new middleware
```

---

## 🔐 Security Improvements

1. **Token Abilities**
   - Client-specific access control
   - Fine-grained permissions
   - Prevents cross-client token usage

2. **Middleware Protection**
   - Mobile endpoints reject web tokens
   - Web endpoints reject mobile tokens
   - Clear 403 error messages

3. **Token Management**
   - Old tokens deleted on new login
   - Separate token names per client
   - Current token invalidation on logout

---

## 🧪 Testing

### New Test Cases Required

**Authentication:**
- [ ] Mobile login returns token with `mobile:access`
- [ ] Web login returns token with `web:access`
- [ ] Mobile token cannot access web endpoints
- [ ] Web token cannot access mobile endpoints
- [ ] Invalid credentials return 401
- [ ] Logout invalidates token

**Authorization:**
- [ ] Protected endpoints require valid token
- [ ] Expired tokens return 401
- [ ] Missing token returns 401
- [ ] Invalid token returns 401

**Versioning:**
- [ ] All v1 endpoints respond correctly
- [ ] Non-existent versions return 404
- [ ] Health check returns version info

---

## 📊 Impact Analysis

### Breaking Changes
⚠️ **All existing API clients must be updated**

**Mobile App (Flutter):**
- Update all endpoint URLs to include `/v1/`
- Change login endpoint from `/mobile/login` to `/v1/mobile/login`
- Update base URL configuration

**Web Client (Future):**
- No impact (not yet implemented)
- Will use new `/v1/web/` endpoints from the start

### Database Impact
- ✅ No database schema changes required
- ✅ Existing data remains compatible
- ℹ️ Token format unchanged (Sanctum standard)

### Performance Impact
- ✅ No significant performance changes
- ℹ️ Additional middleware check (negligible overhead)
- ✅ Token creation performance unchanged

---

## 🚀 Migration Guide

### For Backend Developers
1. Pull latest code from repository
2. Review new middleware files
3. Understand token abilities system
4. Update any custom routes to use `/v1/` prefix

### For Frontend Developers (Flutter)
1. Update API base URL configuration
2. Add `/v1/` prefix to all endpoint constants
3. Update login/register/logout endpoint paths
4. Test authentication flow thoroughly
5. Verify all data endpoints work with new URLs

### Example Migration
```dart
// Before
class ApiEndpoints {
  static const login = '/api/mobile/login';
  static const workorder = '/api/workorder';
}

// After
class ApiEndpoints {
  static const baseUrl = 'https://api.yourdomain.com/api/v1';
  static const login = '$baseUrl/mobile/login';
  static const workorder = '$baseUrl/workorder';
}
```

---

## 📚 Documentation

### Available Documentation
- **[API Refactoring Guide](docs/API_REFACTORING_GUIDE.md)** - Complete guide
- **[Quick Reference](docs/API_QUICK_REFERENCE.md)** - Quick lookup
- **[Postman Collection](docs/Postman_Collection.json)** - Import and test
- **[README](README_REFACTORING.md)** - Overview and quick start

### Documentation Updates Required
- [ ] Update API documentation
- [ ] Update frontend integration guide  
- [ ] Update deployment procedures
- [ ] Update team wiki

---

## 🔮 Future Enhancements

### Planned for v1.1
- Rate limiting per client type
- API response standardization
- Request logging by client
- Token refresh mechanism

### Planned for v2
- GraphQL support
- Webhook system
- Enhanced admin capabilities
- Public API access

---

## ✅ Checklist for Deployment

### Before Deployment
- [ ] All tests pass
- [ ] Documentation complete
- [ ] Migration guide reviewed
- [ ] Team notified of changes
- [ ] Frontend code updated
- [ ] Postman collection tested

### After Deployment
- [ ] Monitor error logs
- [ ] Verify authentication works
- [ ] Check token creation
- [ ] Validate middleware functioning
- [ ] Confirm mobile app compatibility

---

## 👥 Contributors

- Backend Team
- API Architecture Team

---

## 📞 Support

For questions or issues related to this refactoring:
- Review documentation in `docs/` folder
- Contact backend team
- Create issue in repository

---

**Refactoring Completed:** November 1, 2025  
**Status:** ✅ Ready for Testing  
**Next Version:** v1.1 (TBD)

