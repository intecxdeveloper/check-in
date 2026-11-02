<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\GeocodeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeholder_contact_is_stripped_from_user_agent(): void
    {
        config(['expo.geocoder_user_agent' => 'ExpoVisitorMap/1.0 (booth check-in; contact: booth@example.com)']);

        $this->assertStringNotContainsString('example.com', GeocodeController::userAgent());
        $this->assertStringStartsWith('ExpoVisitorMap/1.0', GeocodeController::userAgent());
    }

    public function test_search_uses_nominatim_biased_to_malaysia(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'display_name' => 'Mid Valley, Kuala Lumpur, Malaysia', 'name' => 'Mid Valley',
                'lat' => '3.11', 'lon' => '101.67',
                'address' => ['city' => 'Kuala Lumpur', 'country' => 'Malaysia'],
            ]]),
        ]);

        $this->getJson('/api/geocode?q=mid+valley')
            ->assertOk()
            ->assertJsonPath('results.0.city', 'Kuala Lumpur')
            ->assertJsonPath('results.0.name', 'Mid Valley');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'countrycodes=my')
            && ! str_contains($r->header('User-Agent')[0] ?? '', 'example.com'));
    }

    public function test_search_falls_back_to_photon_when_nominatim_blocks(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response('Access denied', 403),
            'photon.komoot.io/*' => Http::response(['features' => [[
                'geometry' => ['coordinates' => [101.67, 3.11]],
                'properties' => ['name' => 'Mid Valley Megamall', 'city' => 'Kuala Lumpur', 'country' => 'Malaysia', 'countrycode' => 'MY'],
            ]]]),
        ]);

        $this->getJson('/api/geocode?q=mid+valley')
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Mid Valley Megamall')
            ->assertJsonPath('results.0.latitude', 3.11);
    }

    public function test_search_reports_unavailable_when_all_services_fail(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);

        $this->getJson('/api/geocode?q=penang')->assertStatus(503)->assertJsonPath('results', []);
    }
}
