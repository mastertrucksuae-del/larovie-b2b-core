<?php

namespace Tests\Feature;

use App\Filament\Resources\Inquiries\Pages\EditInquiry;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InquiryPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_status_moves_inquiry_along_the_pipeline(): void
    {
        $this->actingAs(User::factory()->create());

        $inquiry = Inquiry::factory()->create(['status' => Inquiry::STATUS_NEW]);
        InquiryItem::factory()->for($inquiry)->create();

        Livewire::test(EditInquiry::class, ['record' => $inquiry->getKey()])
            ->call('setStatus', Inquiry::STATUS_RESPONDING);

        $this->assertSame(Inquiry::STATUS_RESPONDING, $inquiry->refresh()->status);
    }

    public function test_set_status_ignores_unknown_stage(): void
    {
        $this->actingAs(User::factory()->create());

        $inquiry = Inquiry::factory()->create(['status' => Inquiry::STATUS_NEW]);

        Livewire::test(EditInquiry::class, ['record' => $inquiry->getKey()])
            ->call('setStatus', 'bogus');

        $this->assertSame(Inquiry::STATUS_NEW, $inquiry->refresh()->status);
    }
}
