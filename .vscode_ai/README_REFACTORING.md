# API Refactoring - Work Order Management System

## 📖 Documentation Overview

This directory contains comprehensive documentation for the API refactoring to a multi-client architecture.

### 📄 Available Documentation

1. **[API Refactoring Guide](docs/API_REFACTORING_GUIDE.md)** - Complete documentation
   - Architecture overview
   - Detailed explanations
   - Migration guide
   - Troubleshooting
   - Best practices

2. **[API Quick Reference](docs/API_QUICK_REFERENCE.md)** - Daily development reference
   - Quick endpoint lookup
   - Common requests
   - Error codes
   - Migration checklist

3. **[Postman Collection](docs/Postman_Collection.json)** - Ready-to-import collection
   - All endpoints configured
   - Auto-save token scripts
   - Example requests

---

## 🚀 Quick Start

### For Developers New to This Project

1. **Read the Quick Reference**
   ```bash
   docs/API_QUICK_REFERENCE.md
   ```

2. **Import Postman Collection**
   - Open Postman
   - File → Import
   - Select `docs/Postman_Collection.json`
   - Set environment variable `base_url` to your API URL

3. **Test the API**
   - Try "Health Check" endpoint
   - Test "Mobile Login"
   - Token will auto-save for protected endpoints

### For Team Members Updating Existing Code

1. **Review Migration Guide** in the main documentation
2. **Update all URLs** to include `/v1/` prefix
3. **Test in development** before deploying

---

## 📊 What Changed?

### Before
```
/api/mobile/login
/api/mobile/register
/api/workorder
```

### After
```
/api/v1/mobile/login
/api/v1/mobile/register
/api/v1/workorder
```

**Key Addition:** All routes now include `/v1/` for versioning

---

## 🔑 Key Features

- ✅ **API Versioning** - Ready for v2 when needed
- ✅ **Client-Specific Auth** - Mobile and Web have separate tokens
- ✅ **Token Abilities** - Fine-grained access control
- ✅ **Consistent URLs** - Predictable patterns
- ✅ **Future-Proof** - Easy to extend

---

## 🧪 Testing

### Using Postman
1. Import the collection: `docs/Postman_Collection.json`
2. Set `base_url` environment variable
3. Run "Mobile Login" request
4. Token auto-saves for other requests

### Using cURL
```bash
# Login
curl -X POST http://localhost:8000/api/v1/mobile/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Get workorders (use token from login response)
curl -X GET http://localhost:8000/api/v1/workorder \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 📂 File Structure

```
project/
├── app/
│   └── Http/
│       ├── Controllers/
│       │   └── AuthController.php          ← New auth methods
│       ├── Middleware/
│       │   ├── EnsureMobileClient.php      ← New: Mobile validation
│       │   └── EnsureWebClient.php         ← New: Web validation
│       └── Kernel.php                      ← Updated: Middleware registration
├── routes/
│   └── api.php                             ← Restructured routes
├── docs/
│   ├── API_REFACTORING_GUIDE.md            ← Complete documentation
│   ├── API_QUICK_REFERENCE.md              ← Quick lookup
│   └── Postman_Collection.json             ← Postman import file
└── README_REFACTORING.md                   ← This file
```

---

## 🎯 Common Tasks

### For Backend Developers

**Adding a new endpoint:**
```php
// In routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('v1/new-endpoint', [YourController::class, 'method']);
});
```

**Making it client-specific:**
```php
Route::prefix('v1/mobile')->group(function () {
    Route::middleware(['auth:sanctum', 'client.mobile'])->group(function () {
        Route::get('mobile-only', [YourController::class, 'method']);
    });
});
```

### For Frontend Developers

**Update API calls in Flutter:**
```dart
// Before
const String url = '/api/workorder';

// After
const String url = '/api/v1/workorder';
```

**Use helper class:**
```dart
class ApiConfig {
  static const String base = 'https://api.yourdomain.com/api/v1';
  static const String mobileLogin = '$base/mobile/login';
  static const String workorder = '$base/workorder';
}
```

---

## ❓ Need Help?

1. **Quick question?** → Check [Quick Reference](docs/API_QUICK_REFERENCE.md)
2. **Detailed info?** → Read [Full Guide](docs/API_REFACTORING_GUIDE.md)
3. **Testing?** → Use [Postman Collection](docs/Postman_Collection.json)
4. **Still stuck?** → Contact backend team

---

## 📞 Support

**Backend Team Contact:**
- Create an issue in the repository
- Contact via team chat
- Email: backend-team@yourdomain.com

---

## 🔄 Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Nov 1, 2025 | Initial refactoring to v1 API structure |

---

## 📚 Additional Resources

- [Laravel Sanctum Docs](https://laravel.com/docs/sanctum)
- [API Versioning Best Practices](https://restfulapi.net/versioning/)
- [RESTful API Design Guide](https://restfulapi.net/)

---

**Last Updated:** November 1, 2025  
**Maintained By:** Backend Development Team

