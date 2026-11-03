<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Service;
use App\Models\Visitor;
use App\Support\MapCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicMapExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_companies_api_returns_a_json_feature_list(): void
    {
        $company = Company::query()->create([
            'name' => 'Pin Visible Sdn Bhd',
            'city' => 'Klang',
            'country' => 'Malaysia',
            'latitude' => 3.0449,
            'longitude' => 101.4456,
            'public_display_enabled' => true,
        ]);

        $response = $this->getJson(route('api.map.companies'));

        $response->assertOk()
            ->assertJsonPath('type', 'FeatureCollection');

        $features = $response->json('features');
        $this->assertIsArray($features);
        $this->assertTrue(array_is_list($features));
        $this->assertSame($company->id, $features[0]['properties']['id']);
    }

    public function test_homepage_recovers_when_centroid_cache_holds_an_eloquent_model(): void
    {
        Cache::put(MapCache::publicKey('centroid'), Company::query()->make(['name' => 'Stale']), now()->addMinutes(2));

        $this->get(route('map'))->assertOk();
    }

    public function test_homepage_renders_live_public_map_chrome(): void
    {
        $response = $this->get(route('map'));

        $response->assertOk()
            ->assertSee('Skip to check-in', false)
            ->assertSee('class="expo-pulse', false)
            ->assertSee('id="recent-list"', false)
            ->assertSee('Just joined', false)
            ->assertSee('Check in on this phone', false)
            ->assertSee('maplibre-gl.js', false);
    }

    public function test_check_in_page_skips_the_booth_map_bundle(): void
    {
        $response = $this->get(route('check-in'));

        $response->assertOk()
            ->assertSee('Join the Malaysia map', false)
            ->assertSee('Use my location', false)
            ->assertSee('id="prize-receipt"', false)
            ->assertDontSee('maplibre-gl.js', false)
            ->assertDontSee('qrcode.min.js', false)
            ->assertDontSee('See the live map', false);
    }

    public function test_recent_feed_hides_private_visitor_details(): void
    {
        $service = Service::query()->firstOrCreate(
            ['slug' => 'gis'],
            ['name' => 'GIS', 'color' => '#38bdf8'],
        );

        $public = Company::query()->create([
            'name' => 'Open Maps Sdn Bhd',
            'city' => 'George Town',
            'region' => 'Penang',
            'country' => 'Malaysia',
            'latitude' => 5.4141,
            'longitude' => 100.3288,
            'public_display_enabled' => true,
        ]);
        $public->services()->attach($service);

        $private = Company::query()->create([
            'name' => 'Quiet Desk Sdn Bhd',
            'city' => 'Johor Bahru',
            'latitude' => 1.4927,
            'longitude' => 103.7414,
            'public_display_enabled' => false,
        ]);

        Visitor::query()->create([
            'full_name' => 'Aina Public',
            'email' => 'aina@example.com',
            'company_id' => $public->id,
            'display_message' => 'Hello from Penang',
            'privacy_notice_version' => '1.0',
            'submission_fingerprint' => 'public-1',
        ]);

        Visitor::query()->create([
            'full_name' => 'Private Guest',
            'email' => 'private@example.com',
            'company_id' => $private->id,
            'display_message' => 'Do not show this',
            'privacy_notice_version' => '1.0',
            'submission_fingerprint' => 'private-1',
        ]);

        $response = $this->getJson(route('api.map.recent'));

        $response->assertOk()
            ->assertJsonPath('items.0.public', false)
            ->assertJsonPath('items.0.company', null)
            ->assertJsonPath('items.0.message', null)
            ->assertJsonPath('items.1.company', 'Open Maps Sdn Bhd')
            ->assertJsonPath('items.1.message', 'Hello from Penang');

        $this->assertStringNotContainsString('Do not show this', $response->getContent());
        $this->assertStringNotContainsString('Quiet Desk Sdn Bhd', $response->getContent());
    }
}
