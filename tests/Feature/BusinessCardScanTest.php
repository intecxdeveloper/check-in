<?php

namespace Tests\Feature;

use App\Models\BusinessCard;
use App\Models\BusinessCardScanSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessCardScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_staff_card_desk(): void
    {
        $this->get(route('staff.cards'))->assertRedirect('/staff/login');
        $this->get(route('staff.cards.feed'))->assertRedirect('/staff/login');
    }

    public function test_staff_card_desk_shows_a_phone_qr_and_gallery(): void
    {
        $staff = User::factory()->create();

        $html = $this->actingAs($staff)
            ->get(route('staff.cards'))
            ->assertOk()
            ->assertSee('Business cards', false)
            ->assertSee('Phone camera', false)
            ->assertSee('Gallery', false)
            ->assertSee('Recorded details', false)
            ->getContent();

        $this->assertMatchesRegularExpression('#https://scan\.nadispatial\.com/scan/[a-z0-9]{32}#', $html);
        $this->assertStringNotContainsString('http://localhost:8000/scan/', $html);
        $this->assertSame(1, BusinessCardScanSession::query()->count());
    }

    public function test_unknown_scan_token_returns_404(): void
    {
        $this->get('/scan/'.Str::lower(Str::random(32)))->assertNotFound();
    }

    public function test_expired_scan_token_returns_404(): void
    {
        $token = Str::lower(Str::random(32));
        BusinessCardScanSession::factory()->expired()->withToken($token)->create();

        $this->get('/scan/'.$token)->assertNotFound();
    }

    public function test_revoked_scan_token_returns_404(): void
    {
        $token = Str::lower(Str::random(32));
        BusinessCardScanSession::factory()->revoked()->withToken($token)->create();

        $this->get('/scan/'.$token)->assertNotFound();
    }

    public function test_phone_can_open_the_camera_page_with_a_live_token(): void
    {
        $token = Str::lower(Str::random(32));
        BusinessCardScanSession::factory()->withToken($token)->create();

        $this->get('/scan/'.$token)
            ->assertOk()
            ->assertSee('id="card-shutter"', false)
            ->assertSee('aria-label="Snap card"', false)
            ->assertSee('Album', false);
    }

    public function test_phone_upload_stores_the_photo_as_base64_before_ocr(): void
    {
        $token = Str::lower(Str::random(32));
        $session = BusinessCardScanSession::factory()->withToken($token)->create();
        $file = UploadedFile::fake()->image('card.jpg', 1800, 1000);
        $dataUri = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($file->getRealPath()));

        $this->postJson('/scan/'.$token, [
            'image_base64' => $dataUri,
        ])->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('card.full_name', null);

        $card = BusinessCard::query()->first();
        $this->assertNotNull($card);
        $this->assertSame($session->user_id, $card->user_id);
        $this->assertSame($session->id, $card->scan_session_id);
        $this->assertNull($card->full_name);
        $this->assertNotEmpty($card->image_base64);
        $this->assertSame('image/webp', $card->image_mime);
        $binary = $card->imageBinary();
        $this->assertNotNull($binary);
        [$width] = getimagesizefromstring($binary);
        $this->assertLessThanOrEqual(1600, $width);

        $feed = $this->actingAs(User::factory()->create())
            ->getJson(route('staff.cards.feed'))
            ->assertOk()
            ->json();
        $this->assertArrayNotHasKey('image_base64', $feed['cards'][0]);
        $this->assertStringContainsString('/staff/cards/'.$card->id.'/image', $feed['cards'][0]['image_url']);
        $this->assertSame(route('staff.cards.ocr', $card), $feed['cards'][0]['ocr_url']);
    }

    public function test_phone_cannot_submit_ocr_text(): void
    {
        $card = BusinessCard::factory()->unread()->create();

        $this->patchJson(route('staff.cards.ocr', $card), ['ocr_text' => 'Aina Rahman'])
            ->assertUnauthorized();
        $this->assertNull($card->fresh()->ocr_completed_at);
    }

    public function test_desk_ocr_fills_the_card_and_marks_it_read(): void
    {
        $card = BusinessCard::factory()->unread()->create();

        $this->actingAs(User::factory()->create())
            ->patchJson(route('staff.cards.ocr', $card), [
                'ocr_text' => "Aina Rahman\nGeospatial Director\nNADI Spatial Sdn Bhd\naina@nadispatial.my\n0123456789",
            ])->assertOk()
            ->assertJsonPath('card.full_name', 'Aina Rahman')
            ->assertJsonPath('card.company_name', 'NADI Spatial Sdn Bhd')
            ->assertJsonPath('card.email', 'aina@nadispatial.my')
            ->assertJsonPath('card.ocr_url', null);

        $this->assertNotNull($card->fresh()->ocr_completed_at);
    }

    public function test_desk_ocr_keeps_details_staff_already_typed(): void
    {
        $card = BusinessCard::factory()->unread()->create(['full_name' => 'Typed By Staff']);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('staff.cards.ocr', $card), [
                'ocr_text' => "Aina Rahman\naina@nadispatial.my",
            ])->assertOk()
            ->assertJsonPath('card.full_name', 'Typed By Staff')
            ->assertJsonPath('card.email', 'aina@nadispatial.my');
    }

    public function test_desk_ocr_replaces_the_photo_with_the_upright_version(): void
    {
        $sideways = UploadedFile::fake()->image('card.jpg', 450, 800);
        $upright = UploadedFile::fake()->image('card.jpg', 800, 450);
        $toDataUri = fn (UploadedFile $file): string => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($file->getRealPath()));
        $token = Str::lower(Str::random(32));
        BusinessCardScanSession::factory()->withToken($token)->create();

        $store = $this->postJson('/scan/'.$token, ['image_base64' => $toDataUri($sideways)])->assertCreated();
        $card = BusinessCard::query()->findOrFail($store->json('card.id'));

        $this->actingAs(User::factory()->create())
            ->patchJson(route('staff.cards.ocr', $card), [
                'ocr_text' => 'Aina Rahman',
                'image_base64' => $toDataUri($upright),
            ])->assertOk();

        [$width, $height] = getimagesizefromstring((string) $card->fresh()->imageBinary());
        $this->assertGreaterThan($height, $width);
    }

    public function test_phone_upload_rejects_a_non_image(): void
    {
        Storage::fake('public');
        $token = Str::lower(Str::random(32));
        BusinessCardScanSession::factory()->withToken($token)->create();

        $this->postJson('/scan/'.$token, [
            'image_base64' => 'data:image/jpeg;base64,'.base64_encode('<?php echo 1;'),
        ])->assertUnprocessable();

        $this->assertSame(0, BusinessCard::query()->count());
    }

    public function test_staff_feed_returns_gallery_cards(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create();
        $card = BusinessCard::factory()->create([
            'user_id' => $staff->id,
            'full_name' => 'Rina Maps',
        ]);

        $this->actingAs($staff)
            ->getJson(route('staff.cards.feed'))
            ->assertOk()
            ->assertJsonPath('latest_id', $card->id)
            ->assertJsonPath('cards.0.full_name', 'Rina Maps');
    }

    public function test_staff_can_update_and_delete_a_card(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('business-cards/keep.webp', 'webp');
        $staff = User::factory()->create();
        $card = BusinessCard::factory()->create([
            'user_id' => $staff->id,
            'image_path' => 'business-cards/keep.webp',
            'full_name' => 'Old Name',
        ]);

        $this->actingAs($staff)
            ->from(route('staff.cards'))
            ->patch(route('staff.cards.update', $card), [
                'full_name' => 'New Name',
                'job_title' => 'Director',
                'company_name' => 'Open Maps Sdn Bhd',
                'email' => 'new@example.com',
                'phone' => '0112223333',
                'notes' => 'Met at booth',
            ])
            ->assertRedirect(route('staff.cards'))
            ->assertSessionHas('status', 'Card details saved.');

        $this->assertSame('New Name', $card->fresh()->full_name);
        $this->assertSame('Met at booth', $card->fresh()->notes);

        $this->actingAs($staff)
            ->from(route('staff.cards'))
            ->delete(route('staff.cards.destroy', $card))
            ->assertRedirect(route('staff.cards'));

        $this->assertModelMissing($card);
        Storage::disk('public')->assertMissing('business-cards/keep.webp');
    }

    public function test_staff_can_view_the_stored_card_image(): void
    {
        $token = Str::lower(Str::random(32));
        BusinessCardScanSession::factory()->withToken($token)->create();
        $file = UploadedFile::fake()->image('card.jpg', 640, 360);
        $dataUri = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($file->getRealPath()));
        $store = $this->postJson('/scan/'.$token, ['image_base64' => $dataUri])->assertCreated();
        $card = BusinessCard::query()->findOrFail($store->json('card.id'));

        $this->get(route('staff.cards.image', $card))->assertRedirect('/staff/login');

        $this->actingAs(User::factory()->create())
            ->get(route('staff.cards.image', $card))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
    }

    public function test_new_qr_revokes_the_previous_phone_link(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('staff.cards'))->assertOk();
        $oldToken = session('business_card_scan_token');
        $this->assertNotNull($oldToken);

        $this->actingAs($staff)
            ->post(route('staff.cards.link'))
            ->assertRedirect(route('staff.cards'));

        $this->get('/scan/'.$oldToken)->assertNotFound();
        $this->assertSame(1, BusinessCardScanSession::query()->usable()->count());
    }

    public function test_scan_and_card_routes_are_rate_limited(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertContains('throttle:60,1', $routes->getByName('scan.show')->gatherMiddleware());
        $this->assertContains('throttle:30,1', $routes->getByName('scan.store')->gatherMiddleware());
    }
}
