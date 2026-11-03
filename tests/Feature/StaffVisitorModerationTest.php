<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use App\Models\Visitor;
use App\Support\MapCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffVisitorModerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeVisitorWithMessage(string $message = 'Please hide this rude note'): Visitor
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
            'display_message' => $message,
            'company_id' => $company->id,
            'privacy_notice_version' => '1.0',
        ]);
        Lead::query()->create(['visitor_id' => $visitor->id, 'status' => 'new']);

        return $visitor;
    }

    public function test_guest_cannot_delete_a_visitor_message(): void
    {
        $visitor = $this->makeVisitorWithMessage();

        $this->delete(route('staff.visitors.message.destroy', $visitor))
            ->assertRedirect('/staff/login');

        $this->assertSame('Please hide this rude note', $visitor->fresh()->display_message);
    }

    public function test_staff_can_remove_an_inappropriate_public_message(): void
    {
        $visitor = $this->makeVisitorWithMessage();
        $this->getJson(route('api.map.recent'))->assertOk()
            ->assertJsonPath('items.0.message', 'Please hide this rude note');

        $this->actingAs(User::factory()->create())
            ->from(route('staff.visitors'))
            ->delete(route('staff.visitors.message.destroy', $visitor))
            ->assertRedirect(route('staff.visitors'))
            ->assertSessionHas('status', 'Public message removed from the live map.');

        $visitor->refresh();
        $this->assertNull($visitor->display_message);
        $this->assertNotNull($visitor->display_message_removed_at);

        $recent = $this->getJson(route('api.map.recent'))->assertOk();
        $this->assertNull($recent->json('items.0.message'));
        $this->assertStringNotContainsString('Please hide this rude note', $recent->getContent());

        $this->getJson(route('api.map.companies'))->assertOk()
            ->assertJsonPath('features.0.properties.message', '');
    }

    public function test_staff_visitor_list_shows_the_map_message_and_remove_action(): void
    {
        $visitor = $this->makeVisitorWithMessage('Hello from the booth');

        $this->actingAs(User::factory()->create())
            ->get(route('staff.visitors'))
            ->assertOk()
            ->assertSee('Hello from the booth', false)
            ->assertSee('Remove from map', false)
            ->assertSee($visitor->claimCode(), false);
    }

    public function test_staff_can_filter_visitors_that_have_a_map_message(): void
    {
        $withMessage = $this->makeVisitorWithMessage('Shown on map');
        $company = Company::query()->create([
            'name' => 'Quiet Co',
            'city' => 'Ipoh',
            'country' => 'Malaysia',
            'latitude' => 4.6,
            'longitude' => 101.1,
            'public_display_enabled' => true,
        ]);
        $without = Visitor::query()->create([
            'full_name' => 'Silent Guest',
            'email' => 'silent@example.com',
            'company_id' => $company->id,
            'privacy_notice_version' => '1.0',
        ]);
        Lead::query()->create(['visitor_id' => $without->id, 'status' => 'new']);

        $this->actingAs(User::factory()->create())
            ->get(route('staff.visitors', ['messages' => 1]))
            ->assertOk()
            ->assertSee($withMessage->full_name, false)
            ->assertDontSee($without->full_name, false);
    }

    public function test_csv_export_includes_claim_code_and_public_message(): void
    {
        $visitor = $this->makeVisitorWithMessage('=cmd|calc');

        $csv = $this->actingAs(User::factory()->create())
            ->get(route('staff.export'))
            ->streamedContent();

        $this->assertStringContainsString($visitor->claimCode(), $csv);
        $this->assertStringContainsString("'=cmd|calc", $csv);
        $this->assertStringContainsString('Public Message', $csv);
    }

    public function test_removing_a_message_bumps_the_public_map_cache(): void
    {
        $before = MapCache::version();
        $visitor = $this->makeVisitorWithMessage();

        $this->actingAs(User::factory()->create())
            ->delete(route('staff.visitors.message.destroy', $visitor))
            ->assertRedirect();

        $this->assertSame($before + 1, MapCache::version());
    }

    public function test_overview_explains_each_nadi_follow_up_status(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('NADI follow-up', false)
            ->assertSee('How you are following up with each visitor. Not their company.', false)
            ->assertSee('Just checked in. Nobody from NADI has acted yet.', false)
            ->assertSee('Worth a real NADI conversation.', false);
    }

    public function test_visitor_desk_has_no_handled_by_assignment(): void
    {
        $this->makeVisitorWithMessage();
        User::factory()->create(['name' => 'Amina Lee']);

        $this->actingAs(User::factory()->create(['name' => 'Booth Lead']))
            ->get(route('staff.visitors'))
            ->assertOk()
            ->assertSee("Status is NADI's follow-up with this visitor, not their company.", false)
            ->assertDontSee('Handled by', false)
            ->assertDontSee('Anyone at NADI', false);
    }
}
