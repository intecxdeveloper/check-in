<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Models\Visitor;
use App\Support\GiftWheel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftSpinTest extends TestCase
{
    use RefreshDatabase;

    private function makeVisitor(): Visitor
    {
        $company = Company::query()->create([
            'name' => 'Open Maps Sdn Bhd',
            'city' => 'Klang',
            'country' => 'Malaysia',
            'latitude' => 3.0449,
            'longitude' => 101.4456,
            'public_display_enabled' => true,
        ]);

        $visitor = Visitor::query()->create([
            'full_name' => 'Aina Public',
            'email' => 'aina@example.com',
            'company_id' => $company->id,
            'privacy_notice_version' => '1.0',
        ]);
        Lead::query()->create(['visitor_id' => $visitor->id, 'status' => 'new']);

        return $visitor;
    }

    public function test_live_map_shows_the_gift_wheel(): void
    {
        $this->get(route('map'))
            ->assertOk()
            ->assertSee('id="gift-modal"', false)
            ->assertSee('Spin for a gift', false)
            ->assertSee('Gift wheel', false)
            ->assertSee('data-gift-prizes', false);
    }

    public function test_spin_requires_a_claim_code(): void
    {
        $this->postJson(route('api.gift-spin'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('claim_code');
    }

    public function test_spin_returns_404_for_an_unknown_claim_code(): void
    {
        $this->postJson(route('api.gift-spin'), ['claim_code' => 'CK-9999'])
            ->assertNotFound()
            ->assertJsonPath('message', 'That claim code is not on the desk. Check the receipt and try again.');
    }

    public function test_master_key_spin_returns_a_prize_without_attaching_a_visitor(): void
    {
        $keys = array_column(GiftWheel::prizes(), 'key');

        $first = $this->postJson(route('api.gift-spin'), ['claim_code' => 'NADI-SPIN']);
        $second = $this->postJson(route('api.gift-spin'), ['claim_code' => 'nadi-spin']);

        $first->assertOk()
            ->assertJsonPath('already_spun', false)
            ->assertJsonPath('master_spin', true)
            ->assertJsonPath('claim_code', null);

        $second->assertOk()->assertJsonPath('master_spin', true);

        $this->assertTrue(in_array($first->json('prize.key'), $keys, true));
        $this->assertSame(0, Visitor::query()->whereNotNull('gift_prize')->count());
    }

    public function test_repeated_master_key_spins_can_land_on_different_gifts(): void
    {
        $keys = [];

        for ($i = 0; $i < 24; $i++) {
            $response = $this->postJson(route('api.gift-spin'), ['claim_code' => 'NADI-SPIN']);
            $response->assertOk()->assertJsonPath('master_spin', true);
            $keys[] = $response->json('prize.key');
        }

        $this->assertGreaterThan(1, count(array_unique($keys)));
    }

    public function test_live_map_does_not_print_the_gift_master_key(): void
    {
        $this->get(route('map'))
            ->assertOk()
            ->assertDontSee('NADI-SPIN', false)
            ->assertDontSee('gift_master_key', false);
    }

    public function test_spin_assigns_a_configured_gift_to_the_visitor(): void
    {
        $visitor = $this->makeVisitor();
        $keys = array_column(GiftWheel::prizes(), 'key');

        $response = $this->postJson(route('api.gift-spin'), [
            'claim_code' => $visitor->claimCode(),
        ]);

        $response->assertOk()
            ->assertJsonPath('already_spun', false)
            ->assertJsonPath('claim_code', $visitor->claimCode())
            ->assertJsonPath('first_name', 'Aina');

        $key = $response->json('prize.key');
        $this->assertTrue(in_array($key, $keys, true));
        $this->assertTrue(in_array($response->json('prize.label'), array_column(GiftWheel::prizes(), 'label'), true));

        $visitor->refresh();
        $this->assertSame($key, $visitor->gift_prize);
        $this->assertNotNull($visitor->gift_spun_at);
        $this->assertSame($response->json('prize.label'), $visitor->giftPrizeLabel());
    }

    public function test_a_second_spin_returns_the_same_gift(): void
    {
        $visitor = $this->makeVisitor();
        $visitor->update(['gift_prize' => 'tote', 'gift_spun_at' => now()]);

        $this->postJson(route('api.gift-spin'), ['claim_code' => $visitor->claimCode()])
            ->assertOk()
            ->assertJsonPath('already_spun', true)
            ->assertJsonPath('prize.key', 'tote')
            ->assertJsonPath('prize.label', 'NADI tote bag');

        $this->assertSame('tote', $visitor->fresh()->gift_prize);
    }

    public function test_staff_visitor_desk_shows_the_gift(): void
    {
        $visitor = $this->makeVisitor();
        $visitor->update(['gift_prize' => 'tumbler', 'gift_spun_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get(route('staff.visitors'))
            ->assertOk()
            ->assertSee('NADI tumbler', false);
    }
}
