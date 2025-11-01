# API Quick Reference Guide

**Quick lookup for daily development tasks**

---

## 🔗 Base URL
```
Development: http://localhost:8000/api
Production: https://yourdomain.com/api
```

---

## 🚀 Quick Start

### 1. Mobile Login
```bash
POST /api/v1/mobile/login

{
  "email": "user@example.com",
  "password": "password123"
}

Response: { "access_token": "...", "user": {...} }
```

### 2. Use Token
```bash
GET /api/v1/workorder
Authorization: Bearer YOUR_TOKEN_HERE
```

### 3. Logout
```bash
POST /api/v1/mobile/logout
Authorization: Bearer YOUR_TOKEN_HERE
```

---

## 📍 All Endpoints

### Auth Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/v1/mobile/login` | POST | No | Mobile login |
| `/v1/mobile/register` | POST | No | Mobile register |
| `/v1/mobile/logout` | POST | Yes | Mobile logout |
| `/v1/mobile/me` | GET | Yes | Get mobile user |
| `/v1/web/login` | POST | No | Web login |
| `/v1/web/logout` | POST | Yes | Web logout |
| `/v1/web/me` | GET | Yes | Get web user |

### Data Endpoints (All require auth)

| Resource | Endpoint | Methods |
|----------|----------|---------|
| Workorder | `/v1/workorder` | GET, POST, PUT, DELETE |
| Jenis Workorder | `/v1/jenis-workorder` | GET, POST, PUT, DELETE |
| Jenis Lokasi | `/v1/jenis-lokasi` | GET, POST, PUT, DELETE |
| Progress Workorder | `/v1/progress-workorder` | GET, POST, PUT, DELETE |
| Detail Progress | `/v1/detail-progress` | GET, POST, PUT, DELETE |
| Lembur SPL | `/v1/lembur-spl` | GET, POST, PUT |
| KPI | `/v1/kpi` | GET |
| User | `/v1/user` | GET |
| Pegawai | `/v1/pegawai` | GET |
| Master Location | `/v1/master-location` | GET, POST |
| Workorder Action | `/v1/workorder-action` | GET |
| Detail Form | `/v1/detail-form` | GET |

---

## 🎯 Common Requests

### Login (Mobile)
```http
POST /api/v1/mobile/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

### Register (Mobile)
```http
POST /api/v1/mobile/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "role_id": 2
}
```

### Get Workorders
```http
GET /api/v1/workorder
Authorization: Bearer {token}
```

### Create Workorder
```http
POST /api/v1/workorder
Authorization: Bearer {token}
Content-Type: application/json

{
  // workorder data
}
```

### Get Progress by Workorder
```http
GET /api/v1/progress-workorder?workorder_id=1
Authorization: Bearer {token}
```

---

## ⚠️ Common Errors

| Code | Message | Solution |
|------|---------|----------|
| 401 | Unauthenticated | Add/check Authorization header |
| 403 | Access denied | Wrong client token (mobile vs web) |
| 404 | Not found | Check URL includes `/v1/` |
| 422 | Validation error | Check request body fields |

---

## 💡 Tips

### Postman Auto-Save Token
Add to login request's "Tests" tab:
```javascript
pm.environment.set("token", pm.response.json().access_token);
```

### cURL Example
```bash
# Login
curl -X POST http://localhost:8000/api/v1/mobile/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Get workorders
curl -X GET http://localhost:8000/api/v1/workorder \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🔄 Migration Checklist

Moving from old API structure:

- [ ] `/mobile/login` → `/v1/mobile/login`
- [ ] `/mobile/register` → `/v1/mobile/register`
- [ ] `/mobile/logout` → `/v1/mobile/logout`
- [ ] `/mobile/me` → `/v1/mobile/me`
- [ ] `/workorder` → `/v1/workorder`
- [ ] `/kpi` → `/v1/kpi`
- [ ] All other endpoints add `/v1/` prefix

---

**Need more details?** See [API_REFACTORING_GUIDE.md](./API_REFACTORING_GUIDE.md)

