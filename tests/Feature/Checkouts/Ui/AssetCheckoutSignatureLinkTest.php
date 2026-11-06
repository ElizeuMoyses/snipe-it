<?php

namespace Tests\Feature\Checkouts\Ui;

use App\Events\CheckoutableCheckedOut;
use App\Models\Asset;
use App\Models\CheckoutAcceptance;
use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetCheckoutSignatureLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public static function redirectOptions(): array
    {
        return [['index'], ['item'], ['target'], ['back']];
    }

    #[DataProvider('redirectOptions')]
    public function test_checkout_exposes_the_new_term_without_changing_the_selected_destination(string $option): void
    {
        $asset = Asset::factory()->requiresAcceptance()->create(['asset_tag' => 'LINK-001']);
        $assignee = User::factory()->create(['email' => null]);
        $operator = User::factory()->checkoutAssets()->viewAssets()->viewUsers()->create();
        $destinations = [
            'index' => route('hardware.index'),
            'item' => route('hardware.show', $asset),
            'target' => route('users.show', $assignee),
            'back' => route('hardware.index'),
        ];

        $response = $this->actingAs($operator)
            ->withSession(['url.intended' => route('hardware.index')])
            ->post(route('hardware.checkout.store', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => $assignee->id,
                'redirect_option' => $option,
            ])->assertRedirect($destinations[$option]);

        $acceptance = CheckoutAcceptance::where('checkoutable_id', $asset->id)->sole();
        $response->assertSessionHas('checkout_signature_links', function ($links) use ($asset, $assignee, $acceptance) {
            return is_array($links) && count($links) === 1
                && $links[0]['acceptance_id'] === $acceptance->id
                && $links[0]['asset_tag'] === 'LINK-001'
                && $links[0]['assignee'] === $assignee->getFullNameAttribute()
                && $links[0]['url'] === url('/eula/sign/'.$acceptance->token);
        });
        $this->assertTrue($asset->fresh()->assignedTo->is($assignee));
        $this->assertNotEmpty($acceptance->token);
        $this->assertCount(1, CheckoutAcceptance::all());
    }

    public function test_the_term_is_shown_once_and_uses_escaped_asset_and_user_names(): void
    {
        $asset = Asset::factory()->requiresAcceptance()->create(['name' => '<script>alert(1)</script>']);
        $assignee = User::factory()->create(['first_name' => '<b>Example</b>', 'last_name' => 'User']);
        $this->actingAs(User::factory()->checkoutAssets()->viewAssets()->create())
            ->post(route('hardware.checkout.store', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => $assignee->id,
                'redirect_option' => 'item',
                'name' => $asset->name,
            ])->assertSessionHas('checkout_signature_links');

        $acceptance = CheckoutAcceptance::where('checkoutable_id', $asset->id)->sole();
        $this->get(route('hardware.show', $asset))
            ->assertOk()
            ->assertSee('id="checkout-signature-modal"', false)
            ->assertSee($acceptance->token)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('hardware.show', $asset))->assertDontSee('id="checkout-signature-modal"', false);
    }

    public function test_no_term_is_shown_for_an_asset_without_required_acceptance(): void
    {
        $asset = Asset::factory()->doesNotRequireAcceptance()->create();
        $this->actingAs(User::factory()->checkoutAssets()->create())
            ->post(route('hardware.checkout.store', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
                'redirect_option' => 'index',
            ])->assertSessionHas('success')->assertSessionMissing('checkout_signature_links');
        $this->assertDatabaseCount('checkout_acceptances', 0);
    }

    public function test_no_term_is_shown_when_the_target_is_a_location(): void
    {
        $this->actingAs(User::factory()->checkoutAssets()->create())
            ->post(route('hardware.checkout.store', Asset::factory()->requiresAcceptance()->create()), [
                'checkout_to_type' => 'location',
                'assigned_location' => Location::factory()->create()->id,
                'redirect_option' => 'index',
            ])->assertSessionHas('success')->assertSessionMissing('checkout_signature_links');
        $this->assertDatabaseCount('checkout_acceptances', 0);
    }

    public function test_denied_checkout_does_not_generate_or_expose_a_term(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('hardware.checkout.store', Asset::factory()->requiresAcceptance()->create()), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->create()->id,
                'redirect_option' => 'index',
            ])->assertForbidden()->assertSessionMissing('checkout_signature_links');
        $this->assertDatabaseCount('checkout_acceptances', 0);
    }

    public function test_invalid_checkout_does_not_generate_or_expose_a_term(): void
    {
        $this->actingAs(User::factory()->checkoutAssets()->create())
            ->post(route('hardware.checkout.store', Asset::factory()->requiresAcceptance()->create()), [
                'checkout_to_type' => 'user',
                'assigned_user' => 'invalid-user',
                'redirect_option' => 'index',
            ])->assertSessionHasErrors()->assertSessionMissing('checkout_signature_links');
        $this->assertDatabaseCount('checkout_acceptances', 0);
    }

    public function test_old_acceptance_is_not_presented_as_a_new_term_if_no_new_acceptance_was_created(): void
    {
        $asset = Asset::factory()->requiresAcceptance()->create();
        $assignee = User::factory()->create();
        $old = CheckoutAcceptance::create([
            'checkoutable_type' => Asset::class,
            'checkoutable_id' => $asset->id,
            'assigned_to_id' => $assignee->id,
        ]);
        $old->generateToken();
        // Simulate an unavailable acceptance listener, while keeping the real checkout.
        Event::fake([CheckoutableCheckedOut::class]);

        $this->actingAs(User::factory()->checkoutAssets()->create())
            ->post(route('hardware.checkout.store', $asset), [
                'checkout_to_type' => 'user',
                'assigned_user' => $assignee->id,
                'redirect_option' => 'index',
            ])->assertSessionHas('success')->assertSessionMissing('checkout_signature_links');
        $this->assertDatabaseCount('checkout_acceptances', 1);
        $this->assertSame($old->token, $old->fresh()->token);
    }

    public function test_cross_company_checkout_does_not_expose_a_term(): void
    {
        $this->settings->enableMultipleFullCompanySupport();
        $this->actingAs(User::factory()->superuser()->create())
            ->post(route('hardware.checkout.store', Asset::factory()->for(Company::factory())->create()), [
                'checkout_to_type' => 'user',
                'assigned_user' => User::factory()->for(Company::factory())->create()->id,
                'redirect_option' => 'index',
            ])->assertSessionHas('error')->assertSessionMissing('checkout_signature_links');
        $this->assertDatabaseCount('checkout_acceptances', 0);
    }

    public function test_bulk_checkout_groups_only_new_terms_for_the_selected_assets(): void
    {
        $assets = Asset::factory()->requiresAcceptance()->count(2)->sequence(
            ['asset_tag' => 'LINK-BULK-001'],
            ['asset_tag' => 'LINK-BULK-002'],
        )->create();
        $noAcceptance = Asset::factory()->doesNotRequireAcceptance()->create();
        $assignee = User::factory()->create();
        $unrelated = CheckoutAcceptance::factory()->pending()->create();
        $unrelated->generateToken();

        $response = $this->actingAs(User::factory()->checkoutAssets()->viewAssets()->create())
            ->post(route('hardware.bulkcheckout.store'), [
                'selected_assets' => [...$assets->modelKeys(), $noAcceptance->id],
                'checkout_to_type' => 'user',
                'assigned_user' => $assignee->id,
            ])->assertRedirect(url('hardware'))->assertSessionHas('success');

        $response->assertSessionHas('checkout_signature_links', function ($links) use ($unrelated) {
            $this->assertCount(2, $links ?? []);
            $this->assertEqualsCanonicalizing(['LINK-BULK-001', 'LINK-BULK-002'], array_column($links, 'asset_tag'));
            $this->assertNotContains($unrelated->id, array_column($links, 'acceptance_id'));

            return true;
        });
        $this->assertDatabaseCount('checkout_acceptances', 3);
    }
}
