<?php

namespace Tests\Feature;

use App\Models\Gift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffGiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_gifts_menu(): void
    {
        $this->get(route('staff.gifts'))->assertRedirect('/staff/login');
        $this->post(route('staff.gifts.store'), [
            'label' => 'NADI mug',
            'short' => 'Mug',
            'color' => '#00254e',
        ])->assertRedirect('/staff/login');
        $this->patch(route('staff.gifts.master-key'), [
            'master_key' => 'BOOTH-KEY',
        ])->assertRedirect('/staff/login');
    }

    public function test_staff_gifts_page_shows_the_add_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.gifts'))
            ->assertOk()
            ->assertSee('Add a gift', false)
            ->assertSee('Load starter gifts', false)
            ->assertSee('Master spin key', false)
            ->assertSee('NADI-SPIN', false);
    }

    public function test_staff_can_save_a_gift_master_key_and_spin_with_it(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('staff.gifts'))
            ->patch(route('staff.gifts.master-key'), [
                'master_key' => 'booth-key',
            ])
            ->assertRedirect(route('staff.gifts'))
            ->assertSessionHas('status');

        $this->get(route('staff.gifts'))
            ->assertOk()
            ->assertSee('BOOTH-KEY', false);

        $this->postJson(route('api.gift-spin'), ['claim_code' => 'BOOTH-KEY'])
            ->assertOk()
            ->assertJsonPath('master_spin', true);

        $this->postJson(route('api.gift-spin'), ['claim_code' => 'NADI-SPIN'])
            ->assertNotFound();
    }

    public function test_staff_cannot_save_a_visitor_claim_code_as_the_master_key(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('staff.gifts'))
            ->patch(route('staff.gifts.master-key'), [
                'master_key' => 'CK-0001',
            ])
            ->assertRedirect(route('staff.gifts'))
            ->assertSessionHasErrors('master_key');
    }

    public function test_staff_can_add_a_gift_to_the_wheel(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('staff.gifts'))
            ->post(route('staff.gifts.store'), [
                'label' => 'NADI mug',
                'short' => 'Mug',
                'color' => '#00254e',
            ])
            ->assertRedirect(route('staff.gifts'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('gifts', [
            'label' => 'NADI mug',
            'short' => 'Mug',
            'key' => 'nadi-mug',
        ]);

        $this->get(route('map'))
            ->assertOk()
            ->assertSee('NADI mug', false)
            ->assertSee('Mug', false);
    }

    public function test_staff_can_update_and_remove_a_gift(): void
    {
        $gift = Gift::query()->create([
            'key' => 'old-prize',
            'label' => 'Old prize',
            'short' => 'Old',
            'color' => '#00254e',
            'weight' => 1,
            'sort_order' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->from(route('staff.gifts'))
            ->patch(route('staff.gifts.update', $gift), [
                'label' => 'New prize',
                'short' => 'New',
                'color' => '#c8141e',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('staff.gifts'));

        $gift->refresh();
        $this->assertSame('New prize', $gift->label);
        $this->assertSame('old-prize', $gift->key);

        $this->from(route('staff.gifts'))
            ->delete(route('staff.gifts.destroy', $gift))
            ->assertRedirect(route('staff.gifts'));

        $this->assertModelMissing($gift);
    }

    public function test_staff_can_load_starter_gifts(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('staff.gifts'))
            ->post(route('staff.gifts.starter'))
            ->assertRedirect(route('staff.gifts'));

        $this->assertGreaterThanOrEqual(8, Gift::query()->count());
        $this->assertDatabaseHas('gifts', ['key' => 'tote', 'label' => 'NADI tote bag']);
    }

    public function test_adding_a_gift_requires_a_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('staff.gifts'))
            ->post(route('staff.gifts.store'), [
                'label' => '',
                'color' => '#00254e',
            ])
            ->assertRedirect(route('staff.gifts'))
            ->assertSessionHasErrors('label');
    }
}
