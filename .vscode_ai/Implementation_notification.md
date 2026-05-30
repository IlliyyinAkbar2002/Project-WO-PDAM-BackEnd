Short answer: \*\*yes, for a real work-order notification feature, notifications should be stored in the backend database\*\*.

For your stack, the clean architecture would be:

\- \*\*Flutter\*\*: displays notifications, unread count, mark as read.

\- \*\*Laravel 8\*\*: creates notifications when work-order events happen.

\- \*\*PostgreSQL\*\*: stores notification records.

You only skip backend storage if the notification is purely local, for example a temporary in-app reminder that does not need to survive logout, reinstall, or another device.

For your thesis app, backend storage is recommended because notifications may be tied to business events like:

\- new work order assigned

\- work order status changed

\- report submitted

\- report approved/rejected

\- quota/daily report limit reached

\- admin updates task data

A simple PostgreSQL table could be like:

\`\`\`text

notifications

\- id

\- user\_id

\- type

\- title

\- message

\- data jsonb

\- read\_at nullable

\- created\_at

\- updated\_at

\`\`\`

Then Flutter can call APIs such as:

\`\`\`text

GET /api/notifications

POST /api/notifications/{id}/read

POST /api/notifications/read-all

\`\`\`

If you later use push notifications with FCM, still store the notification in PostgreSQL. FCM should be treated as the delivery mechanism, while the database remains the source of truth.

Backend side: **yes, a `notifications` table is necessary** if you want notifications to be persistent, readable/unread, visible after logout/login, and synced across devices. For a thesis/work-order app, I would store them in PostgreSQL.

The minimum direct relation is:

```text
notifications
- id
- recipient_user_id -> users.id
- actor_user_id nullable -> users.id
- type
- title
- message
- data jsonb nullable
- read_at nullable
- created_at
- updated_at
```

For your app, the most important related tables are:

```text
users
workorders
assignment_workorders / assignees
progress_workorders
lembur_spl
peminjaman_material
```

Recommended optional FKs:

```text
workorder_id nullable -> workorders.id
assignment_workorder_id nullable -> assignment_workorders.id
progress_workorder_id nullable -> progress_workorders.id
lembur_spl_id nullable -> lembur_spl.id
peminjaman_material_id nullable -> peminjaman_material.id
```

Do **not** directly relate notifications to every master table like `m_status`, `m_jenis_workorder`, `roles`, `departments`, or `materials` unless there is a strong reason. Those are usually accessed through the source record, or copied into the notification `data` JSON as a snapshot.

Example events:

```text
Work order assigned
-> recipient_user_id = assigned staff
-> workorder_id
-> assignment_workorder_id

Progress submitted
-> recipient_user_id = supervisor/assigner
-> workorder_id
-> progress_workorder_id

Progress approved/revision requested
-> recipient_user_id = staff who submitted progress
-> workorder_id
-> progress_workorder_id

SPL/lembur approved or rejected
-> recipient_user_id = requester
-> lembur_spl_id
-> optionally workorder_id

Material loan/return event
-> recipient_user_id = related staff/admin
-> workorder_id
-> peminjaman_material_id
```

Laravel also has a built-in database notification table using `notifiable_type`, `notifiable_id`, and `data`. That works, but for your thesis ERD I would prefer a custom `notifications` table with explicit `recipient_user_id` and optional domain FKs because it is easier to explain and query.

I could not inspect `app/`, `database/`, and `routes/` in this workspace because this checkout only exposes the Flutter project. So confirm the exact table names from your Laravel migrations before applying the FK names above.