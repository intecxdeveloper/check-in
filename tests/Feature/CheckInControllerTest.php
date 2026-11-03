<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Service;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_stores_typed_other_interest_and_shows_it_on_the_map(): void
    {
        $other = $this->otherService();

        $response = $this->postJson('/api/check-in', $this->payload([
            'services' => [$other->id],
            'other_interest' => 'Hydrography',
        ]));

        $visitor = Visitor::query()->latest('id')->first();

        $response->assertCreated();
        $this->assertSame('Hydrography', $visitor->other_interest);
        $this->assertSame('Hydrography', $visitor->company->other_interest);

        $services = json_decode((string) $response->json('company.properties.services'), true);
        $this->assertSame('Hydrography', $services[0]['name']);
        $this->assertSame('other', $services[0]['slug']);

        $map = $this->getJson(route('api.map.companies'));
        $mapServices = json_decode((string) $map->json('features.0.properties.services'), true);
        $this->assertSame('Hydrography', $mapServices[0]['name']);
    }

    public function test_check_in_returns_422_when_other_is_selected_without_a_typed_interest(): void
    {
        $other = $this->otherService();

        $this->postJson('/api/check-in', $this->payload([
            'services' => [$other->id],
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.other_interest.0', 'Type the interest you have in mind.');

        $this->assertSame(0, Visitor::query()->count());
    }

    public function test_check_in_ignores_other_interest_when_other_is_not_selected(): void
    {
        $gis = Service::query()->firstOrCreate(
            ['slug' => 'gis'],
            ['name' => 'GIS', 'color' => '#38bdf8'],
        );

        $this->postJson('/api/check-in', $this->payload([
            'services' => [$gis->id],
            'other_interest' => 'Should ignore',
        ]))->assertCreated();

        $visitor = Visitor::query()->latest('id')->first();
        $this->assertNull($visitor->other_interest);
        $this->assertNull($visitor->company->other_interest);
    }

    public function test_check_in_returns_422_when_other_interest_contains_markup(): void
    {
        $other = $this->otherService();

        $this->postJson('/api/check-in', $this->payload([
            'services' => [$other->id],
            'other_interest' => '<script>alert(1)</script>',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('other_interest');

        $this->assertSame(0, Visitor::query()->count());
    }

    public function test_check_in_page_includes_other_interest_field(): void
    {
        $this->get(route('check-in'))
            ->assertOk()
            ->assertSee('name="other_interest"', false)
            ->assertSee('Type anything', false);
    }

    public function test_staff_visitors_page_escapes_typed_other_interest(): void
    {
        $other = $this->otherService();
        $company = Company::query()->create([
            'name' => 'Open Maps Sdn Bhd',
            'city' => 'Klang',
            'country' => 'Malaysia',
            'latitude' => 3.04,
            'longitude' => 101.44,
            'public_display_enabled' => true,
            'other_interest' => '<img src=x onerror=alert(1)>',
        ]);
        $company->services()->attach($other);

        Visitor::query()->create([
            'full_name' => 'Aina Public',
            'email' => 'aina@example.com',
            'company_id' => $company->id,
            'other_interest' => '<img src=x onerror=alert(1)>',
            'privacy_notice_version' => '1.0',
            'submission_fingerprint' => 'public-1',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('staff.visitors'))
            ->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    private function otherService(): Service
    {
        return Service::query()->firstOrCreate(
            ['slug' => 'other'],
            ['name' => 'Other', 'color' => '#94a3b8'],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Aina Test',
            'email' => 'aina@example.com',
            'company_name' => 'Test Mapping Sdn Bhd',
            'latitude' => 3.1,
            'longitude' => 101.6,
            'public_display_enabled' => '1',
            'follow_up_consent' => '0',
            'privacy_accepted' => '1',
        ], $overrides);
    }
}
