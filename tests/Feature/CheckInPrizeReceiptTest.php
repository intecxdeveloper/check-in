<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInPrizeReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_returns_prize_claim_receipt_fields(): void
    {
        $service = Service::firstOrCreate(['slug' => 'gis'], ['name' => 'GIS', 'color' => '#38bdf8']);

        $response = $this->postJson('/api/check-in', [
            'full_name' => 'Aina Test',
            'email' => 'aina@example.com',
            'company_name' => 'Test Mapping Sdn Bhd',
            'latitude' => 3.1,
            'longitude' => 101.6,
            'public_display_enabled' => '1',
            'follow_up_consent' => '0',
            'privacy_accepted' => '1',
            'services' => [$service->id],
        ]);

        $visitor = Visitor::latest('id')->first();

        $response->assertCreated()
            ->assertJsonPath('full_name', 'Aina Test')
            ->assertJsonPath('company_name', 'Test Mapping Sdn Bhd')
            ->assertJsonPath('claim_code', $visitor->claimCode())
            ->assertJsonPath('visitor_id', $visitor->id);

        $this->assertSame('CK-'.str_pad((string) $visitor->id, 4, '0', STR_PAD_LEFT), $visitor->claimCode());
    }

    public function test_phone_check_in_page_shows_prize_receipt_not_map_link(): void
    {
        $this->get(route('check-in'))
            ->assertOk()
            ->assertSee('id="prize-receipt"', false)
            ->assertSee('Show this screen at the counter to claim your prize.', false)
            ->assertSee(asset(config('expo.brand_logo')), false)
            ->assertDontSee('See the live map', false)
            ->assertDontSee('success-map-link', false);
    }
}
