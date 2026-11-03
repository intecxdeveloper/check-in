<?php

namespace Tests\Feature;

use App\Http\Controllers\Staff\VisitorController;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeVisitor(bool $public, string $company = 'Hidden Co'): Visitor
    {
        $companyModel = Company::create([
            'name' => $company,
            'city' => 'Subang Jaya',
            'country' => 'Malaysia',
            'latitude' => 3.05,
            'longitude' => 101.58,
            'public_display_enabled' => $public,
        ]);

        $visitor = Visitor::create([
            'full_name' => 'Secret Person',
            'email' => 'secret@example.com',
            'phone' => '0123456789',
            'display_message' => 'private hello',
            'company_id' => $companyModel->id,
            'privacy_notice_version' => '2026-10',
        ]);
        Lead::create(['visitor_id' => $visitor->id, 'status' => 'new']);

        return $visitor;
    }

    private function checkInPayload(array $overrides = []): array
    {
        $service = Service::firstOrCreate(['slug' => 'gis'], ['name' => 'GIS', 'color' => '#38bdf8']);

        return array_merge([
            'full_name' => 'Aina Test',
            'email' => 'aina@example.com',
            'company_name' => 'Test Mapping Sdn Bhd',
            'latitude' => 3.1,
            'longitude' => 101.6,
            'public_display_enabled' => '1',
            'follow_up_consent' => '0',
            'privacy_accepted' => '1',
            'services' => [$service->id],
        ], $overrides);
    }

    public function test_staff_pages_require_login(): void
    {
        $this->get('/staff')->assertRedirect('/staff/login');
        $this->get('/staff/visitors')->assertRedirect('/staff/login');
        $this->get('/staff/export')->assertRedirect('/staff/login');
        $visitor = $this->makeVisitor(true);
        $this->delete(route('staff.visitors.message.destroy', $visitor))->assertRedirect('/staff/login');
        $lead = $visitor->lead;
        $this->patch("/staff/leads/{$lead->id}", ['status' => 'closed'])->assertRedirect('/staff/login');
        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_public_apis_never_expose_contact_details(): void
    {
        $this->makeVisitor(true, 'Open Co');

        foreach (['/api/map/companies', '/api/map/recent', '/api/companies/search?q=Open'] as $url) {
            $body = $this->getJson($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('secret@example.com', $body, $url);
            $this->assertStringNotContainsString('0123456789', $body, $url);
            $this->assertStringNotContainsString('Secret Person', $body, $url);
        }
    }

    public function test_private_companies_stay_off_public_feeds(): void
    {
        $this->makeVisitor(false, 'Hidden Co');

        $this->getJson('/api/map/companies')->assertOk()->assertJsonCount(0, 'features');
        $recent = $this->getJson('/api/map/recent')->assertOk()->getContent();
        $this->assertStringNotContainsString('Hidden Co', $recent);
        $this->assertStringNotContainsString('private hello', $recent);
        $this->assertStringNotContainsString('Subang', $recent);
        $this->assertStringNotContainsString('Hidden Co', $this->getJson('/api/companies/search?q=Hidden')->getContent());
    }

    public function test_check_in_and_login_routes_have_csrf_and_rate_limits(): void
    {
        $routes = app('router')->getRoutes();
        $checkIn = $routes->getByName('api.check-in')->gatherMiddleware();
        $this->assertContains('web', $checkIn);
        $this->assertContains('throttle:60,1', $checkIn);
        $this->assertContains('throttle:10,1', $routes->getByName('staff.login.submit')->gatherMiddleware());
        $this->assertContains('throttle:180,1', $routes->getByName('api.companies.search')->gatherMiddleware());
    }

    public function test_avatar_upload_is_reencoded_and_rejects_non_images(): void
    {
        Storage::fake('public');

        $this->postJson('/api/check-in', $this->checkInPayload([
            'avatar' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
        ]))->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->postJson('/api/check-in', $this->checkInPayload([
            'avatar' => UploadedFile::fake()->image('me.jpg', 1600, 1200),
        ]))->assertCreated();

        $path = Visitor::latest('id')->first()->avatar_path;
        $this->assertStringEndsWith('.webp', $path);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertLessThanOrEqual(320, $width);
    }

    public function test_check_in_ignores_mass_assignment_fields(): void
    {
        $this->postJson('/api/check-in', $this->checkInPayload([
            'status' => 'closed',
            'staff_notes' => 'pwned',
            'company_id' => 999,
        ]))->assertCreated();

        $lead = Lead::latest('id')->first();
        $this->assertSame('new', $lead->status);
        $this->assertNull($lead->staff_notes);
    }

    public function test_check_in_rejects_javascript_website(): void
    {
        $this->postJson('/api/check-in', $this->checkInPayload([
            'public_website' => 'javascript:alert(1)',
        ]))->assertStatus(422)->assertJsonValidationErrors('public_website');
    }

    public function test_csv_export_neutralises_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", VisitorController::csvSafe('=HYPERLINK("x")'));
        $this->assertSame("'+1", VisitorController::csvSafe('+1'));
        $this->assertSame('Aina', VisitorController::csvSafe('Aina'));

        $visitor = $this->makeVisitor(true);
        $visitor->update(['full_name' => '=cmd|calc']);
        $csv = $this->actingAs(User::factory()->create())->get('/staff/export')->streamedContent();
        $this->assertStringContainsString("'=cmd|calc", $csv);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->actingAs(User::factory()->create())->get('/staff')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_demo_accounts_are_blocked_in_production(): void
    {
        User::factory()->create(['email' => 'staff@expo.test', 'password' => 'password']);
        $this->app['env'] = 'production';

        $this->withSession(['_token' => 'test-token'])
            ->post('/staff/login', ['email' => 'staff@expo.test', 'password' => 'password', '_token' => 'test-token'])
            ->assertRedirect()->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_seeder_skips_demo_credentials_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->seed();

        $this->assertDatabaseMissing('users', ['email' => 'staff@expo.test']);
        $this->assertDatabaseHas('services', ['slug' => 'gis']);
    }

    public function test_health_check_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
