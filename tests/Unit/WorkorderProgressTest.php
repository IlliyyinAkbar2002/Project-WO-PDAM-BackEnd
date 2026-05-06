<?php

namespace Tests\Unit;

use App\Models\Status;
use App\Models\Workorder;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class WorkorderProgressTest extends TestCase
{
    public function test_progres_persen_is_0_when_status_is_initial()
    {
        $status = new Status(['kode' => 'DITUGASKAN_KE_SPV']);
        $wo = new Workorder();
        $wo->setRelation('status', $status);

        $this->assertEquals(0, $wo->progres_persen);
    }

    public function test_progres_persen_is_100_when_status_is_selesai()
    {
        $status = new Status(['kode' => 'SELESAI']);
        $wo = new Workorder();
        $wo->setRelation('status', $status);

        $this->assertEquals(100, $wo->progres_persen);
    }

    public function test_progres_persen_is_90_when_status_is_pengecekan()
    {
        $status = new Status(['kode' => 'PENGECEKAN']);
        $wo = new Workorder();
        $wo->setRelation('status', $status);

        $this->assertEquals(90, $wo->progres_persen);
    }

    public function test_progres_persen_is_50_when_in_progress_but_missing_dates()
    {
        $status = new Status(['kode' => 'IN_PROGRESS']);
        $wo = new Workorder();
        $wo->setRelation('status', $status);
        
        $this->assertEquals(50, $wo->progres_persen);
    }

    public function test_progres_persen_is_correct_when_in_progress_and_time_elapsed()
    {
        $status = new Status(['kode' => 'IN_PROGRESS']);
        $wo = new Workorder();
        $wo->setRelation('status', $status);
        
        // Mock current time
        $now = Carbon::create(2026, 5, 5, 12, 0, 0);
        Carbon::setTestNow($now);

        // 48 hours total. 24 hours elapsed. Ratio = 0.5. 
        // 10 + (0.5 * 70) = 45%
        $wo->tanggal_mulai = '2026-05-04 12:00:00';
        $wo->estimasi_selesai = '2026-05-06 12:00:00';

        $this->assertEquals(45, $wo->progres_persen);

        // 12 hours elapsed. Ratio = 0.25.
        // 10 + (0.25 * 70) = 27.5 => 27%
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 0, 0, 0));
        $this->assertEquals(27, $wo->progres_persen);

        // 48 hours elapsed. Ratio = 1.0.
        // 10 + (1.0 * 70) = 80%
        Carbon::setTestNow(Carbon::create(2026, 5, 6, 12, 0, 0));
        $this->assertEquals(80, $wo->progres_persen);
        
        // Overdue. Ratio = 1.0 (clamped).
        // 10 + (1.0 * 70) = 80%
        Carbon::setTestNow(Carbon::create(2026, 5, 7, 12, 0, 0));
        $this->assertEquals(80, $wo->progres_persen);

        // Not started yet (future). Ratio = 0.0 (clamped).
        // 10 + (0.0 * 70) = 10%
        Carbon::setTestNow(Carbon::create(2026, 5, 3, 12, 0, 0));
        $this->assertEquals(10, $wo->progres_persen);

        // Clear mock
        Carbon::setTestNow();
    }
}
