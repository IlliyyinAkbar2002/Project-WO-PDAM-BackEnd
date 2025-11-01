# Architecture Overview - Multi-Client API

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Client Applications                         │
├────────────────────────────────┬────────────────────────────────────┤
│      Flutter Mobile App        │      Web Admin Panel (Future)      │
│                                │                                    │
│  - User management             │  - Admin dashboard                 │
│  - Workorder tracking          │  - Advanced reporting              │
│  - Field operations            │  - System configuration            │
│  - Progress updates            │  - User management                 │
└────────────────┬───────────────┴────────────────┬───────────────────┘
                 │                                │
                 │ HTTPS/JSON                     │ HTTPS/JSON
                 │                                │
┌────────────────▼────────────────────────────────▼───────────────────┐
│                                                                      │
│                         Laravel API Gateway                          │
│                      (nginx + php-fpm)                              │
│                                                                      │
└──────────────────────────────┬───────────────────────────────────────┘
                               │
                               │
┌──────────────────────────────▼───────────────────────────────────────┐
│                     Route Handler (api.php)                          │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │                    /api/v1/...                                 │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                               │                                      │
│         ┌─────────────────────┼─────────────────────┐               │
│         │                     │                     │               │
│  ┌──────▼──────┐      ┌──────▼──────┐      ┌──────▼──────┐        │
│  │   /mobile   │      │    /web     │      │  /shared    │        │
│  │             │      │             │      │  resources  │        │
│  └──────┬──────┘      └──────┬──────┘      └──────┬──────┘        │
│         │                     │                     │               │
└─────────┼─────────────────────┼─────────────────────┼───────────────┘
          │                     │                     │
          │                     │                     │
┌─────────▼─────────────────────▼─────────────────────▼───────────────┐
│                      Middleware Layer                                │
│                                                                      │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐ │
│  │  auth:sanctum    │  │  client.mobile   │  │   client.web     │ │
│  │                  │  │                  │  │                  │ │
│  │  - Verify token  │  │  - Check mobile  │  │  - Check web     │ │
│  │  - Load user     │  │    ability       │  │    ability       │ │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘ │
│                                                                      │
└──────────────────────────────┬───────────────────────────────────────┘
                               │
                               │
┌──────────────────────────────▼───────────────────────────────────────┐
│                        Controllers Layer                             │
│                                                                      │
│  ┌──────────────┐  ┌────────────────┐  ┌──────────────────────┐   │
│  │    Auth      │  │   Workorder    │  │   Progress WO        │   │
│  │  Controller  │  │   Controller   │  │   Controller         │   │
│  └──────────────┘  └────────────────┘  └──────────────────────┘   │
│                                                                      │
│  ┌──────────────┐  ┌────────────────┐  ┌──────────────────────┐   │
│  │     User     │  │      KPI       │  │      Pegawai         │   │
│  │  Controller  │  │   Controller   │  │   Controller         │   │
│  └──────────────┘  └────────────────┘  └──────────────────────┘   │
│                                                                      │
└──────────────────────────────┬───────────────────────────────────────┘
                               │
                               │
┌──────────────────────────────▼───────────────────────────────────────┐
│                        Services Layer                                │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │         ProgressWorkorderService                               │ │
│  │  - addWorkorderProgress()                                      │ │
│  │  - updateStatusOnSubmit()                                      │ │
│  └────────────────────────────────────────────────────────────────┘ │
│                                                                      │
└──────────────────────────────┬───────────────────────────────────────┘
                               │
                               │
┌──────────────────────────────▼───────────────────────────────────────┐
│                         Models Layer                                 │
│                                                                      │
│  ┌─────────┐  ┌─────────┐  ┌────────────┐  ┌──────────────────┐   │
│  │  User   │  │Workorder│  │  Progress  │  │   DetailForm     │   │
│  │         │  │         │  │            │  │                  │   │
│  └─────────┘  └─────────┘  └────────────┘  └──────────────────┘   │
│                                                                      │
└──────────────────────────────┬───────────────────────────────────────┘
                               │
                               │
┌──────────────────────────────▼───────────────────────────────────────┐
│                          Database Layer                              │
│                         (MySQL / PostgreSQL)                         │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  Tables:                                                      │  │
│  │  - users                    - m_workorder                     │  │
│  │  - m_pegawai                - progress_workorder              │  │
│  │  - m_role                   - detail_progress                 │  │
│  │  - m_jenis_workorder        - dokumentasi_progress            │  │
│  │  - m_jenis_lokasi           - personal_access_tokens          │  │
│  │  - m_location               - lembur_spl                      │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 🔐 Authentication Flow

### Mobile Client Authentication

```
┌──────────────┐
│ Mobile App   │
└──────┬───────┘
       │
       │ 1. POST /api/v1/mobile/login
       │    { email, password }
       │
       ▼
┌──────────────────────────────┐
│  AuthController::mobileLogin │
│                              │
│  1. Validate credentials     │
│  2. Find user in DB          │
│  3. Verify password          │
│  4. Delete old mobile tokens │
│  5. Create new token with:   │
│     - mobile:access          │
│     - workorder:read         │
│     - workorder:write        │
└──────┬───────────────────────┘
       │
       │ 2. Return token + user data
       │
       ▼
┌──────────────┐
│ Mobile App   │
│              │
│ Store token  │
│ in secure    │
│ storage      │
└──────┬───────┘
       │
       │ 3. GET /api/v1/workorder
       │    Authorization: Bearer {token}
       │
       ▼
┌──────────────────────────────┐
│  Middleware Stack            │
│                              │
│  1. auth:sanctum             │
│     - Verify token exists    │
│     - Load user from token   │
│                              │
│  2. (optional) client.mobile │
│     - Check mobile:access    │
│     - Return 403 if invalid  │
└──────┬───────────────────────┘
       │
       │ 4. Token valid, proceed
       │
       ▼
┌──────────────────────────────┐
│  WorkorderController::index  │
│                              │
│  - Get workorders from DB    │
│  - Return JSON response      │
└──────┬───────────────────────┘
       │
       │ 5. Return workorder data
       │
       ▼
┌──────────────┐
│ Mobile App   │
│              │
│ Display data │
└──────────────┘
```

---

## 🛣️ Request Flow

### Detailed Request Lifecycle

```
┌─────────────────────────────────────────────────────────────────────┐
│  1. Client Request                                                  │
│                                                                     │
│  GET /api/v1/workorder                                             │
│  Authorization: Bearer 1|xxxxxxxxxxxxx                             │
│  Accept: application/json                                          │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  2. Laravel Entry Point                                             │
│                                                                     │
│  - public/index.php                                                │
│  - Bootstrap application                                           │
│  - Load environment config                                         │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  3. Global Middleware                                               │
│                                                                     │
│  - CORS handling (for web clients)                                 │
│  - Trim strings                                                    │
│  - Convert empty strings to null                                   │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  4. Route Matching                                                  │
│                                                                     │
│  - Match URL to routes/api.php                                     │
│  - Extract route parameters                                        │
│  - Determine controller + method                                   │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  5. Route Middleware                                                │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │  A. auth:sanctum                                            │  │
│  │     - Extract token from Authorization header              │  │
│  │     - Query personal_access_tokens table                   │  │
│  │     - Load associated user                                 │  │
│  │     - Attach user to request                               │  │
│  │     - If invalid: return 401 Unauthorized                  │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │  B. client.mobile (if route requires)                      │  │
│  │     - Get current access token                             │  │
│  │     - Check if has 'mobile:access' ability                 │  │
│  │     - If not: return 403 Forbidden                         │  │
│  └─────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  6. Controller Method                                               │
│                                                                     │
│  WorkorderController::index()                                       │
│  - Access request data                                             │
│  - Interact with models                                            │
│  - Apply business logic                                            │
│  - Call services if needed                                         │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  7. Model Interaction                                               │
│                                                                     │
│  Workorder::query()                                                 │
│  - Build database query                                            │
│  - Execute SQL                                                     │
│  - Fetch results                                                   │
│  - Return Eloquent Collection                                      │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  8. Response Formation                                              │
│                                                                     │
│  - Transform data (if using API Resources)                         │
│  - Format as JSON                                                  │
│  - Set HTTP status code                                            │
│  - Add headers                                                     │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  9. Client Response                                                 │
│                                                                     │
│  HTTP/1.1 200 OK                                                   │
│  Content-Type: application/json                                    │
│                                                                     │
│  [                                                                  │
│    { "id": 1, "title": "Workorder 1", ... },                       │
│    { "id": 2, "title": "Workorder 2", ... }                        │
│  ]                                                                  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🎫 Token Abilities Matrix

| Ability | Mobile Token | Web Token | Description |
|---------|--------------|-----------|-------------|
| `mobile:access` | ✅ Yes | ❌ No | Access mobile-specific endpoints |
| `web:access` | ❌ No | ✅ Yes | Access web-specific endpoints |
| `workorder:read` | ✅ Yes | ✅ Yes | Read workorder data |
| `workorder:write` | ✅ Yes | ✅ Yes | Create/update workorders |
| `admin:access` | ❌ No | ✅ Yes | Admin panel features |

---

## 🔒 Security Layers

```
┌─────────────────────────────────────────────────────────────────────┐
│  Layer 1: Network Security                                          │
│  - HTTPS/TLS encryption                                            │
│  - Firewall rules                                                  │
│  - DDoS protection                                                 │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Layer 2: Application Gateway                                       │
│  - CORS policy                                                     │
│  - Rate limiting                                                   │
│  - Request validation                                              │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Layer 3: Authentication (Sanctum)                                  │
│  - Token validation                                                │
│  - User identification                                             │
│  - Token expiration                                                │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Layer 4: Authorization (Token Abilities)                           │
│  - Client type verification                                        │
│  - Ability-based access control                                    │
│  - Resource permissions                                            │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Layer 5: Business Logic                                            │
│  - Input validation                                                │
│  - Business rules enforcement                                      │
│  - Data sanitization                                               │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Layer 6: Data Access                                               │
│  - SQL injection prevention (Eloquent)                             │
│  - Query parameterization                                          │
│  - Database access control                                         │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📦 Component Dependencies

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Frontend Clients                            │
│                                                                     │
│  ┌──────────────────┐              ┌──────────────────┐           │
│  │  Flutter Mobile  │              │   Web Admin      │           │
│  │                  │              │   (Future)       │           │
│  │  Dependencies:   │              │                  │           │
│  │  - dio/http      │              │  Dependencies:   │           │
│  │  - flutter_bloc  │              │  - axios/fetch   │           │
│  │  - secure_storage│              │  - react/vue     │           │
│  └──────────────────┘              └──────────────────┘           │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      Backend (Laravel)                              │
│                                                                     │
│  Core Dependencies:                                                │
│  - laravel/framework (9.x)                                         │
│  - laravel/sanctum                                                 │
│  - fruitcake/laravel-cors                                          │
│                                                                     │
│  Database:                                                         │
│  - MySQL 8.0+ / PostgreSQL 12+                                     │
│                                                                     │
│  Server:                                                           │
│  - PHP 8.0+                                                        │
│  - nginx / Apache                                                  │
│  - Redis (for caching/queues)                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🚦 API Versioning Strategy

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Version Timeline                             │
│                                                                     │
│  v1 (Current)                v2 (Future)              v3 (Future)  │
│  ┌──────────────┐            ┌──────────────┐        ┌──────────┐ │
│  │ - Basic auth │            │ - OAuth2     │        │ - GraphQL│ │
│  │ - REST API   │ ────────▶  │ - Enhanced   │ ────▶  │ - WebSock│ │
│  │ - Token auth │            │   security   │        │ - gRPC   │ │
│  │ - Mobile/Web │            │ - Rate limit │        │          │ │
│  └──────────────┘            │ - Pagination │        └──────────┘ │
│   Nov 2025                   └──────────────┘         TBD         │
│                               Q1 2026 (est.)                       │
│                                                                     │
│  Backward Compatibility:                                           │
│  - v1 will remain active for 6 months after v2 release            │
│  - Deprecation warnings in v1 when v2 is available                │
│  - Migration guide provided before v2 release                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📊 Data Flow Diagram

### Workorder Creation Flow

```
┌─────────────┐
│ Mobile App  │
└──────┬──────┘
       │
       │ 1. User fills workorder form
       │
       ▼
┌─────────────────────────────┐
│ Validate input locally      │
│ - Required fields           │
│ - Data formats              │
└──────┬──────────────────────┘
       │
       │ 2. POST /api/v1/workorder
       │    + Authorization header
       │    + Workorder data
       ▼
┌─────────────────────────────┐
│ Laravel API                 │
│                             │
│ ┌─────────────────────────┐ │
│ │ Middleware              │ │
│ │ - auth:sanctum          │ │
│ │ - Validate token        │ │
│ └─────────────────────────┘ │
│                             │
│ ┌─────────────────────────┐ │
│ │ WorkorderController     │ │
│ │ - Validate request data │ │
│ │ - Check permissions     │ │
│ └─────────────────────────┘ │
│                             │
│ ┌─────────────────────────┐ │
│ │ Workorder Model         │ │
│ │ - Create record         │ │
│ │ - Save to database      │ │
│ └─────────────────────────┘ │
│                             │
│ ┌─────────────────────────┐ │
│ │ ProgressWorkorderService│ │
│ │ - Create initial        │ │
│ │   progress entries      │ │
│ └─────────────────────────┘ │
└──────┬──────────────────────┘
       │
       │ 3. Return created workorder
       │    + ID
       │    + Initial status
       ▼
┌─────────────┐
│ Mobile App  │
│             │
│ - Show      │
│   success   │
│ - Navigate  │
│   to detail │
└─────────────┘
```

---

## 🔄 State Management

### Token Lifecycle

```
┌─────────────────────────────────────────────────────────────────────┐
│                      Token Lifecycle States                         │
│                                                                     │
│  ┌──────────┐      Login       ┌──────────┐                        │
│  │   NULL   │ ─────────────▶   │  ACTIVE  │                        │
│  │(No token)│                   │  TOKEN   │                        │
│  └──────────┘                   └────┬─────┘                        │
│       ▲                              │                              │
│       │                              │ Used in requests             │
│       │                              │                              │
│       │ Logout                       ▼                              │
│       │                         ┌─────────┐                         │
│       └─────────────────────────│ EXPIRED │                         │
│                                 │ INVALID │                         │
│                                 │ DELETED │                         │
│                                 └─────────┘                         │
│                                                                     │
│  Token Storage:                                                    │
│  - Mobile: Secure storage / Keychain                               │
│  - Web: HTTP-only cookies (recommended) or LocalStorage           │
│                                                                     │
│  Token Metadata:                                                   │
│  - name: 'mobile-token' or 'web-token'                            │
│  - abilities: Array of permissions                                 │
│  - created_at: Timestamp                                           │
│  - last_used_at: Timestamp (auto-updated)                         │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📈 Scalability Considerations

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Horizontal Scaling                               │
│                                                                     │
│  Load Balancer (nginx)                                             │
│         │                                                           │
│         ├──────────┬──────────┬──────────┬──────────┐             │
│         ▼          ▼          ▼          ▼          ▼             │
│    ┌─────┐    ┌─────┐    ┌─────┐    ┌─────┐    ┌─────┐          │
│    │App 1│    │App 2│    │App 3│    │App 4│    │App n│          │
│    └─────┘    └─────┘    └─────┘    └─────┘    └─────┘          │
│         │          │          │          │          │             │
│         └──────────┴──────────┴──────────┴──────────┘             │
│                           │                                        │
│                           ▼                                        │
│                  ┌─────────────────┐                               │
│                  │  Redis Cache    │                               │
│                  │  - Sessions     │                               │
│                  │  - Token cache  │                               │
│                  └─────────────────┘                               │
│                           │                                        │
│                           ▼                                        │
│                  ┌─────────────────┐                               │
│                  │  Database       │                               │
│                  │  Master/Replica │                               │
│                  └─────────────────┘                               │
└─────────────────────────────────────────────────────────────────────┘
```

---

**Document Version:** 1.0  
**Last Updated:** November 1, 2025  
**Maintained By:** Backend Team

