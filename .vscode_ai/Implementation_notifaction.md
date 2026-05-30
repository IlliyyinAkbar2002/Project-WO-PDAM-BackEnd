# Rencana Implementasi Notifikasi (Poin 1: Laravel Database Notification)

Dokumen ini menjelaskan spesifikasi kontrak API, skema database di Backend (Laravel), dan struktur kode di Frontend (Flutter) untuk mengimplementasikan notifikasi independen menggunakan fitur bawaan Laravel Database Notification.

---

## 1. Sisi Backend (Laravel 8)

Backend hanya perlu menggunakan fitur bawaan Laravel Database Notification. Data disimpan di satu tabel mandiri bernama `notifications` tanpa *Foreign Key* fisik ke tabel lain.

### A. Skema Database (Migration)
BE agent dapat membuat tabel notifikasi bawaan Laravel dengan menjalankan perintah:
```bash
php artisan notifications:table
php artisan migrate
```

Skema tabel `notifications` yang terbentuk di PostgreSQL:
```sql
CREATE TABLE notifications (
    id UUID PRIMARY KEY,
    type VARCHAR(255) NOT NULL, -- Class notification di Laravel, misal: App\Notifications\WorkOrderNotification
    notifiable_type VARCHAR(255) NOT NULL, -- Model penerima, misal: App\Models\User atau m_pegawai
    notifiable_id BIGINT NOT NULL, -- ID dari penerima
    data TEXT NOT NULL, -- Data payload dalam format JSON
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### B. Struktur Data Payload (Kolom `data`)
Isi kolom `data` di database berupa JSON terstruktur seperti berikut:

```json
{
  "title": "Tugas Work Order Baru",
  "message": "Superadmin membagikan WO #WO-2026-001 kepada Anda.",
  "work_order_id": 12,
  "type": "wo_created", -- Jenis notifikasi: "wo_created" | "wo_assigned" | "wo_completed"
  "sender_name": "Superadmin"
}
```

### C. Kontrak API Endpoint
BE agent perlu menyediakan 2 endpoint berikut untuk dikonsumsi oleh Flutter:

#### 1. Mendapatkan Daftar Notifikasi
*   **Method:** `GET`
*   **Path:** `/api/notifications`
*   **Headers:** `Authorization: Bearer <token_auth>`
*   **Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Notifications retrieved successfully",
      "data": [
        {
          "id": "7a94d80a-9d95-46ff-b631-c423ba4e6b18",
          "type": "App\\Notifications\\WorkOrderNotification",
          "data": {
            "title": "Tugas Work Order Baru",
            "message": "Superadmin membagikan WO #WO-2026-001 kepada Anda.",
            "work_order_id": 12,
            "type": "wo_created",
            "sender_name": "Superadmin"
          },
          "read_at": null, -- null jika belum dibaca
          "created_at": "2026-05-26T12:00:00.000000Z"
        }
      ]
    }
    ```

#### 2. Menandai Notifikasi sebagai Sudah Dibaca (Mark as Read)
*   **Method:** `PUT` atau `POST`
*   **Path:** `/api/notifications/{id}/read`
*   **Headers:** `Authorization: Bearer <token_auth>`
*   **Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Notification marked as read"
    }
    ```

---

## 2. Sisi Frontend (Flutter)

Flutter akan menggunakan pustaka yang sudah ada di project (`dio`, `retrofit`, `flutter_bloc`, `get_it`) untuk mengambil dan mengelola status notifikasi.

### A. Model Notifikasi (Data Model)
Membuat file model `notification_model.dart` untuk memetakan respon JSON dari API:

```dart
class NotificationModel {
  final String id;
  final String type;
  final NotificationData data;
  final DateTime? readAt;
  final DateTime createdAt;

  NotificationModel({
    required this.id,
    required this.type,
    required this.data,
    this.readAt,
    required this.createdAt,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['id'] as String,
      type: json['type'] as String,
      data: NotificationData.fromJson(json['data'] as Map<String, dynamic>),
      readAt: json['read_at'] != null ? DateTime.parse(json['read_at'] as String) : null,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  bool get isRead => readAt != null;
}

class NotificationData {
  final String title;
  final String message;
  final int workOrderId;
  final String type; // "wo_created" | "wo_assigned" | "wo_completed"
  final String senderName;

  NotificationData({
    required this.title,
    required this.message,
    required this.workOrderId,
    required this.type,
    required this.senderName,
  });

  factory NotificationData.fromJson(Map<String, dynamic> json) {
    return NotificationData(
      title: json['title'] as String,
      message: json['message'] as String,
      workOrderId: json['work_order_id'] as int,
      type: json['type'] as String,
      senderName: json['sender_name'] as String,
    );
  }
}
```

### B. Retrofit API Client
Mendefinisikan service API menggunakan `retrofit` agar otomatis generate kodenya:

```dart
import 'package:retrofit/retrofit.dart';
import 'package:dio/dio.dart';

part 'notification_api.g.dart';

@RestApi()
abstract class NotificationApi {
  factory NotificationApi(Dio dio, {String baseUrl}) = _NotificationApi;

  @GET('/api/notifications')
  Future<HttpResponse<Map<String, dynamic>>> getNotifications();

  @PUT('/api/notifications/{id}/read')
  Future<HttpResponse<Map<String, dynamic>>> markAsRead(@Path('id') String id);
}
```

### C. State Management (Bloc)
Kita akan membuat `NotificationBloc` yang memegang logika bisnis untuk:
1. `FetchNotifications`: Mengambil daftar notifikasi terbaru dari Laravel.
2. `MarkNotificationAsRead`: Mengubah status `read_at` di server saat notifikasi di-klik di UI Flutter.

#### Event:
```dart
abstract class NotificationEvent {}
class FetchNotifications extends NotificationEvent {}
class MarkAsRead extends NotificationEvent {
  final String notificationId;
  MarkAsRead(this.notificationId);
}
```

#### State:
```dart
abstract class NotificationState {}
class NotificationInitial extends NotificationState {}
class NotificationLoading extends NotificationState {}
class NotificationLoaded extends NotificationState {
  final List<NotificationModel> notifications;
  NotificationLoaded(this.notifications);
}
class NotificationError extends NotificationState {
  final String message;
  NotificationError(this.message);
}
```

### D. Integrasi UI ([notifications.dart](file:///f:/Project_mobile_pdam/lib/feature/work_order/presentation/pages/profile/notifications.dart))
Mengganti ListView berisi dummy items di `NotificationsPage` dengan `BlocBuilder<NotificationBloc, NotificationState>`:
1. Menampilkan indikator loading saat memuat data.
2. Memetakan daftar dari `NotificationLoaded` ke widget `_NotificationItem`.
3. Menghitung jumlah notifikasi belum dibaca untuk memperbarui tulisan `"You have X Notifications today."`.
4. Mengubah inisial lingkaran avatar secara dinamis berdasarkan `senderName` (misal: "Superadmin" -> "SA", "Budi" -> "BD").
5. Ketika item notifikasi di-tap, jalankan event `MarkAsRead` ke BLoC dan arahkan pengguna ke detail Work Order sesuai dengan `workOrderId`.
