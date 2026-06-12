<?php

namespace Tests\Unit;

use App\Models\Status;
use App\Models\Workorder;
use App\Models\WorkorderAssignment;
use Illuminate\Support\Carbon;
use Tests\TestCase;

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

}
