<?php

namespace Tests\Unit;

use App\Enums\DocumentType;
use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPeriodModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_job_has_many_periods(): void
    {
        $job = Job::factory()->create();
        JobPeriod::factory()->count(2)->create(['job_id' => $job->id]);

        $this->assertCount(2, $job->periods);
    }

    public function test_a_period_belongs_to_a_job(): void
    {
        $job = Job::factory()->create();
        $period = JobPeriod::factory()->create(['job_id' => $job->id]);

        $this->assertTrue($period->job->is($job));
    }

    public function test_a_period_can_reference_its_previous_period(): void
    {
        $job = Job::factory()->create();
        $oldPeriod = JobPeriod::factory()->create(['job_id' => $job->id]);
        $newPeriod = JobPeriod::factory()->create([
            'job_id' => $job->id,
            'previous_period_id' => $oldPeriod->id,
        ]);

        $this->assertTrue($newPeriod->previousPeriod->is($oldPeriod));
    }

    public function test_status_and_document_type_are_cast_to_enums(): void
    {
        $job = Job::factory()->create();
        $period = JobPeriod::factory()->create([
            'job_id' => $job->id,
            'jenis_dokumen' => DocumentType::PR,
            'status' => JobPeriodStatus::Aktif,
        ]);

        $this->assertInstanceOf(JobStatus::class, $job->status);
        $this->assertInstanceOf(DocumentType::class, $period->jenis_dokumen);
        $this->assertInstanceOf(JobPeriodStatus::class, $period->status);
        $this->assertSame(DocumentType::PR, $period->jenis_dokumen);
    }
}
