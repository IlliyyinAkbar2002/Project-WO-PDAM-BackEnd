# Backend Implementation: `progress_detail` Table

## Overview
Create a new `progress_detail` table to encapsulate all work order progress review data, separating submission (staff) from review (SPV) concerns.

---

## Database Schema Changes

### New Table: `progress_detail`

```sql
CREATE TABLE progress_detail (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  progress_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  reason_for_rejection TEXT NULL,
  field_to_revise VARCHAR(255) NULL COMMENT 'Comma-separated: photo,description,location,etc',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (progress_id) REFERENCES progress(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  
  INDEX idx_progress_id (progress_id),
  INDEX idx_status (status),
  INDEX idx_reviewed_at (reviewed_at)
);
```

### Migration: Remove Columns from `progress` Table

```sql
ALTER TABLE progress
  DROP COLUMN reviewed_at,
  DROP COLUMN reason_for_rejection,
  DROP COLUMN field_to_revise;
```

**Note**: Ensure data migration before dropping columns!

---

## Data Migration Strategy

### Step 1: Migrate Existing Review Data

```sql
-- Migrate existing reviewed progress to progress_detail
INSERT INTO progress_detail (progress_id, status, reviewed_by, reviewed_at, reason_for_rejection, field_to_revise)
SELECT 
  id,
  CASE 
    WHEN reviewed_at IS NOT NULL AND reason_for_rejection IS NULL THEN 'approved'
    WHEN reviewed_at IS NOT NULL AND reason_for_rejection IS NOT NULL THEN 'rejected'
    ELSE 'pending'
  END as status,
  NULL as reviewed_by, -- If you don't track reviewer in old schema
  reviewed_at,
  reason_for_rejection,
  field_to_revise
FROM progress
WHERE reviewed_at IS NOT NULL OR reason_for_rejection IS NOT NULL;
```

### Step 2: Create Pending Records for Unreviewed Progress

```sql
-- Create pending progress_detail for all progress without review
INSERT INTO progress_detail (progress_id, status)
SELECT id, 'pending'
FROM progress
WHERE id NOT IN (SELECT progress_id FROM progress_detail);
```

---

## API Changes

### 1. Staff Submit Progress (Existing Endpoint - Modified Response)

**Endpoint**: `POST /api/work-orders/{wo_id}/progress`

**Request Body** (unchanged):
```json
{
  "tipe_progress_id": 1,
  "description": "Pekerjaan persiapan selesai",
  "photo_url": "https://...",
  "latitude": -6.2088,
  "longitude": 106.8456
}
```

**Response** (NEW - includes auto-created progress_detail):
```json
{
  "success": true,
  "data": {
    "progress": {
      "id": 123,
      "work_order_id": 456,
      "tipe_progress_id": 1,
      "description": "Pekerjaan persiapan selesai",
      "photo_url": "https://...",
      "latitude": -6.2088,
      "longitude": 106.8456,
      "created_at": "2026-05-15T10:30:00Z"
    },
    "progress_detail": {
      "id": 789,
      "progress_id": 123,
      "status": "pending",
      "reviewed_by": null,
      "reviewed_at": null,
      "reason_for_rejection": null,
      "field_to_revise": null
    }
  }
}
```

**Backend Logic**:
```php
// After creating progress record
$progress = Progress::create($validatedData);

// Auto-create pending progress_detail
$progressDetail = ProgressDetail::create([
    'progress_id' => $progress->id,
    'status' => 'pending'
]);

return response()->json([
    'success' => true,
    'data' => [
        'progress' => $progress,
        'progress_detail' => $progressDetail
    ]
]);
```

---

### 2. Staff Resubmit Rejected Progress (NEW Endpoint)

**Endpoint**: `PUT /api/progress/{progress_id}/resubmit`

**Request Body**:
```json
{
  "description": "Updated description",
  "photo_url": "https://new-photo.jpg",
  "latitude": -6.2088,
  "longitude": 106.8456
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "progress": {
      "id": 123,
      "description": "Updated description",
      "photo_url": "https://new-photo.jpg",
      "updated_at": "2026-05-15T14:00:00Z"
    },
    "progress_detail": {
      "id": 790,
      "progress_id": 123,
      "status": "pending",
      "reviewed_at": null
    }
  }
}
```

**Backend Logic**:
```php
public function resubmit(Request $request, $progressId)
{
    $progress = Progress::findOrFail($progressId);
    
    // Verify current status is rejected
    $latestDetail = $progress->progressDetails()->latest()->first();
    if ($latestDetail->status !== 'rejected') {
        return response()->json(['error' => 'Can only resubmit rejected progress'], 400);
    }
    
    // Update progress data
    $progress->update($request->only(['description', 'photo_url', 'latitude', 'longitude']));
    
    // Create NEW progress_detail with pending status
    $newDetail = ProgressDetail::create([
        'progress_id' => $progress->id,
        'status' => 'pending'
    ]);
    
    return response()->json([
        'success' => true,
        'data' => [
            'progress' => $progress->fresh(),
            'progress_detail' => $newDetail
        ]
    ]);
}
```

---

### 3. SPV Approve Progress (Modified Endpoint)

**Endpoint**: `POST /api/progress/{progress_id}/approve`

**Request Body**:
```json
{
  "reviewed_by": 789
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "progress_detail": {
      "id": 790,
      "progress_id": 123,
      "status": "approved",
      "reviewed_by": 789,
      "reviewed_at": "2026-05-15T15:00:00Z"
    },
    "work_order": {
      "id": 456,
      "status": "in_progress",
      "progress_percentage": 66
    }
  }
}
```

**Backend Logic**:
```php
public function approve(Request $request, $progressId)
{
    $progress = Progress::with('workOrder')->findOrFail($progressId);
    
    // Get latest pending progress_detail
    $latestDetail = $progress->progressDetails()
        ->where('status', 'pending')
        ->latest()
        ->first();
    
    if (!$latestDetail) {
        return response()->json(['error' => 'No pending review found'], 400);
    }
    
    // Update progress_detail to approved
    $latestDetail->update([
        'status' => 'approved',
        'reviewed_by' => $request->reviewed_by,
        'reviewed_at' => now()
    ]);
    
    // Recalculate work order progress
    $this->updateWorkOrderProgress($progress->workOrder);
    
    return response()->json([
        'success' => true,
        'data' => [
            'progress_detail' => $latestDetail->fresh(),
            'work_order' => $progress->workOrder->fresh()
        ]
    ]);
}
```

---

### 4. SPV Reject Progress (Modified Endpoint)

**Endpoint**: `POST /api/progress/{progress_id}/reject`

**Request Body**:
```json
{
  "reviewed_by": 789,
  "reason_for_rejection": "Foto kurang jelas, deskripsi tidak lengkap",
  "field_to_revise": "photo,description"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "progress_detail": {
      "id": 790,
      "progress_id": 123,
      "status": "rejected",
      "reviewed_by": 789,
      "reviewed_at": "2026-05-15T15:00:00Z",
      "reason_for_rejection": "Foto kurang jelas, deskripsi tidak lengkap",
      "field_to_revise": "photo,description"
    },
    "work_order": {
      "id": 456,
      "status": "in_progress",
      "progress_percentage": 33
    }
  }
}
```

**Backend Logic**:
```php
public function reject(Request $request, $progressId)
{
    $progress = Progress::with('workOrder')->findOrFail($progressId);
    
    // Get latest pending progress_detail
    $latestDetail = $progress->progressDetails()
        ->where('status', 'pending')
        ->latest()
        ->first();
    
    if (!$latestDetail) {
        return response()->json(['error' => 'No pending review found'], 400);
    }
    
    // Update progress_detail to rejected
    $latestDetail->update([
        'status' => 'rejected',
        'reviewed_by' => $request->reviewed_by,
        'reviewed_at' => now(),
        'reason_for_rejection' => $request->reason_for_rejection,
        'field_to_revise' => $request->field_to_revise
    ]);
    
    // Recalculate work order progress (will decrease if last step was rejected)
    $this->updateWorkOrderProgress($progress->workOrder);
    
    return response()->json([
        'success' => true,
        'data' => [
            'progress_detail' => $latestDetail->fresh(),
            'work_order' => $progress->workOrder->fresh()
        ]
    ]);
}
```

---

### 5. Get Work Order Progress with Review Status (Modified Endpoint)

**Endpoint**: `GET /api/work-orders/{wo_id}/progress`

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "work_order_id": 456,
      "tipe_progress_id": 1,
      "tipe_progress_name": "Persiapan",
      "description": "Pekerjaan persiapan selesai",
      "photo_url": "https://...",
      "latitude": -6.2088,
      "longitude": 106.8456,
      "created_at": "2026-05-15T10:30:00Z",
      "updated_at": "2026-05-15T10:30:00Z",
      "review_history": [
        {
          "id": 789,
          "status": "rejected",
          "reviewed_by": 101,
          "reviewer_name": "Budi Santoso",
          "reviewed_at": "2026-05-15T11:00:00Z",
          "reason_for_rejection": "Foto kurang jelas",
          "field_to_revise": "photo"
        },
        {
          "id": 790,
          "status": "approved",
          "reviewed_by": 101,
          "reviewer_name": "Budi Santoso",
          "reviewed_at": "2026-05-15T15:00:00Z",
          "reason_for_rejection": null,
          "field_to_revise": null
        }
      ],
      "current_status": "approved"
    },
    {
      "id": 124,
      "work_order_id": 456,
      "tipe_progress_id": 2,
      "tipe_progress_name": "Pelaksanaan",
      "description": "Pekerjaan pelaksanaan selesai",
      "photo_url": "https://...",
      "created_at": "2026-05-15T12:00:00Z",
      "review_history": [
        {
          "id": 791,
          "status": "pending",
          "reviewed_by": null,
          "reviewed_at": null,
          "reason_for_rejection": null,
          "field_to_revise": null
        }
      ],
      "current_status": "pending"
    }
  ]
}
```

**Backend Logic**:
```php
public function getProgress($workOrderId)
{
    $progress = Progress::where('work_order_id', $workOrderId)
        ->with([
            'tipeProgress',
            'progressDetails' => function($query) {
                $query->orderBy('created_at', 'desc');
            },
            'progressDetails.reviewer'
        ])
        ->get();
    
    $formattedProgress = $progress->map(function($p) {
        $reviewHistory = $p->progressDetails->map(function($detail) {
            return [
                'id' => $detail->id,
                'status' => $detail->status,
                'reviewed_by' => $detail->reviewed_by,
                'reviewer_name' => $detail->reviewer?->name,
                'reviewed_at' => $detail->reviewed_at,
                'reason_for_rejection' => $detail->reason_for_rejection,
                'field_to_revise' => $detail->field_to_revise
            ];
        });
        
        return [
            'id' => $p->id,
            'work_order_id' => $p->work_order_id,
            'tipe_progress_id' => $p->tipe_progress_id,
            'tipe_progress_name' => $p->tipeProgress->name,
            'description' => $p->description,
            'photo_url' => $p->photo_url,
            'latitude' => $p->latitude,
            'longitude' => $p->longitude,
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
            'review_history' => $reviewHistory,
            'current_status' => $p->progressDetails->first()->status ?? 'pending'
        ];
    });
    
    return response()->json([
        'success' => true,
        'data' => $formattedProgress
    ]);
}
```

---

## Progress Percentage Calculation (CRITICAL)

### Recommended Approach: Conservative Calculation

**Rule**: Only count **approved** steps toward completion percentage.

```php
private function updateWorkOrderProgress(WorkOrder $workOrder)
{
    // Get total number of progress types for this WO category
    $totalSteps = TipeProgress::count(); // Assuming 3: Persiapan, Pelaksanaan, Penyelesaian
    
    // Get all progress for this work order
    $allProgress = $workOrder->progress()->with('progressDetails')->get();
    
    // Count approved steps
    $approvedCount = $allProgress->filter(function($progress) {
        $latestDetail = $progress->progressDetails->first(); // Already ordered by latest
        return $latestDetail && $latestDetail->status === 'approved';
    })->count();
    
    // Calculate percentage
    $percentage = ($approvedCount / $totalSteps) * 100;
    
    // Determine WO status
    $status = 'in_progress';
    if ($percentage === 100) {
        $status = 'completed';
    } elseif ($percentage === 0) {
        $status = 'assigned'; // or keep as 'in_progress' if already started
    }
    
    // Update work order
    $workOrder->update([
        'progress_percentage' => $percentage,
        'status' => $status
    ]);
}
```

### Example Scenarios:

| Step 1 Status | Step 2 Status | Step 3 Status | Percentage | WO Status |
|---------------|---------------|---------------|------------|------------|
| Approved      | Approved      | Approved      | 100%       | completed  |
| Approved      | Approved      | Rejected      | 66%        | in_progress|
| Approved      | Approved      | Pending       | 66%        | in_progress|
| Approved      | Rejected      | Not submitted | 33%        | in_progress|
| Pending       | Pending       | Pending       | 0%         | in_progress|

**Key Points**:
- Rejected step = NOT counted toward percentage
- Pending step = NOT counted toward percentage
- Only approved steps increase percentage
- If last step (Step 3) is rejected → percentage drops from 100% to 66%
- WO status changes from "completed" back to "in_progress"

---

## Model Relationships (Laravel)

### Progress Model

```php
class Progress extends Model
{
    protected $fillable = [
        'work_order_id',
        'tipe_progress_id',
        'description',
        'photo_url',
        'latitude',
        'longitude'
    ];
    
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }
    
    public function tipeProgress()
    {
        return $this->belongsTo(TipeProgress::class);
    }
    
    public function progressDetails()
    {
        return $this->hasMany(ProgressDetail::class)->orderBy('created_at', 'desc');
    }
    
    public function latestProgressDetail()
    {
        return $this->hasOne(ProgressDetail::class)->latestOfMany();
    }
    
    public function getCurrentStatusAttribute()
    {
        return $this->latestProgressDetail?->status ?? 'pending';
    }
}
```

### ProgressDetail Model

```php
class ProgressDetail extends Model
{
    protected $fillable = [
        'progress_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reason_for_rejection',
        'field_to_revise'
    ];
    
    protected $casts = [
        'reviewed_at' => 'datetime'
    ];
    
    public function progress()
    {
        return $this->belongsTo(Progress::class);
    }
    
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

---

## Business Rules Summary

1. **Staff submits progress** → Auto-create `progress_detail` with status `pending`
2. **SPV approves** → Update latest `progress_detail` to `approved`, recalculate percentage
3. **SPV rejects** → Update latest `progress_detail` to `rejected`, recalculate percentage (decreases)
4. **Staff resubmits** → Update `progress` data + create NEW `progress_detail` with status `pending`
5. **SPV cannot un-review** → Once approved/rejected, that record is immutable
6. **Percentage calculation** → Only count approved steps
7. **WO status** → Changes to "completed" only when all steps approved (100%)
8. **Last step rejection** → WO goes back to "in_progress", percentage drops

---

## Testing Checklist

- [ ] Migration runs successfully without data loss
- [ ] Existing progress data migrated to `progress_detail`
- [ ] Staff can submit new progress
- [ ] Staff can resubmit rejected progress
- [ ] SPV can approve pending progress
- [ ] SPV can reject pending progress with reason
- [ ] SPV cannot approve/reject already reviewed progress
- [ ] Percentage calculation is correct for all scenarios
- [ ] WO status updates correctly based on percentage
- [ ] Review history is preserved and ordered correctly
- [ ] API returns correct `current_status` for each progress
- [ ] Rejecting last step (Step 3) decreases percentage from 100% to 66%
- [ ] WO status changes from "completed" to "in_progress" when last step rejected

---

## Notes

- This approach provides full audit trail of all review actions
- Each resubmission creates a new `progress_detail` record
- SPV can see full history of rejections and resubmissions
- Frontend can display timeline of review actions
- Database is normalized and scalable for future requirements
