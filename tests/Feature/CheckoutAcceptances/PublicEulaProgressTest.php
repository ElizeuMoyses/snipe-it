<?php

namespace Tests\Feature\CheckoutAcceptances;

use App\Models\Actionlog;
use App\Models\CheckoutAcceptance;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicEulaProgressTest extends TestCase
{
    private const CPF = '52998224725';
    private const NAME = 'Maria Teste';
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake();
        config(['eula.validation_session_minutes' => 30]);
    }

    private function term(?User $user = null): CheckoutAcceptance
    {
        $user ??= User::factory()->create([
            'first_name' => 'Maria', 'last_name' => 'Teste',
            'employee_num' => self::CPF, 'email' => null,
        ]);
        $term = CheckoutAcceptance::factory()->pending()->create(['assigned_to_id' => $user->id]);
        $term->generateToken();

        return $term;
    }

    private function validateIdentity(CheckoutAcceptance $term): void
    {
        $this->post(route('eula.sign.validate', $term->token), [
            'cpf' => self::CPF, 'full_name' => self::NAME,
        ])->assertRedirect(route('eula.sign.step2', $term->token));
    }

    private function agree(CheckoutAcceptance $term): void
    {
        $this->post(route('eula.sign.accept', $term->token), [
            'action' => 'accept', 'agree' => '1',
        ])->assertRedirect(route('eula.sign.step3', $term->token));
    }

    private function assertUnsigned(CheckoutAcceptance $term): void
    {
        $term->refresh();
        $this->assertNull($term->accepted_at);
        $this->assertNull($term->declined_at);
        $this->assertNull($term->signature_filename);
        $this->assertNotNull($term->token);
        $this->assertSame([], Storage::allFiles('private_uploads/signatures'));
    }

    public static function protectedPages(): array
    {
        return [['eula.sign.step2'], ['eula.sign.step3']];
    }

    #[DataProvider('protectedPages')]
    public function test_a_valid_token_alone_cannot_open_later_steps(string $route): void
    {
        $term = $this->term();
        $this->get(route($route, $term->token))
            ->assertRedirect(route('eula.sign.step1', $term->token));
        $this->assertUnsigned($term);
    }

    public static function decisions(): array
    {
        return [['accept'], ['decline']];
    }

    #[DataProvider('decisions')]
    public function test_direct_agreement_or_decline_requires_identity_validation(string $action): void
    {
        $term = $this->term();
        $this->post(route('eula.sign.accept', $term->token), [
            'action' => $action, 'agree' => '1', 'identity_validated' => true,
        ])->assertRedirect(route('eula.sign.step1', $term->token));
        $this->assertUnsigned($term);
    }

    public function test_direct_signature_post_cannot_bypass_identity_with_client_flags(): void
    {
        $term = $this->term();
        $this->postJson(route('eula.sign.finalize', $term->token), [
            'signature_data' => self::PNG, 'identity_validated' => true,
            'cpf' => self::CPF, 'full_name' => self::NAME, 'agree' => '1',
        ])->assertForbidden()->assertJson([
            'success' => false, 'code' => 'eula_identity_required',
            'redirect_url' => route('eula.sign.step1', $term->token),
        ]);
        $this->assertUnsigned($term);
    }

    public static function incorrectIdentity(): array
    {
        return [
            'wrong CPF' => ['11144477735', self::NAME, 'cpf'],
            'wrong name' => [self::CPF, 'Outra Pessoa', 'full_name'],
        ];
    }

    #[DataProvider('incorrectIdentity')]
    public function test_incorrect_identity_never_grants_progress(string $cpf, string $name, string $field): void
    {
        $term = $this->term();
        $this->post(route('eula.sign.validate', $term->token), [
            'cpf' => $cpf, 'full_name' => $name,
        ])->assertSessionHasErrors($field);
        $this->get(route('eula.sign.step3', $term->token))->assertRedirect(route('eula.sign.step1', $term->token));
        $this->postJson(route('eula.sign.finalize', $term->token), ['signature_data' => self::PNG])->assertForbidden();
        $this->assertSame(1, $term->fresh()->failed_attempts);
        $this->assertUnsigned($term);
    }

    public function test_valid_identity_allows_reading_terms_but_does_not_skip_consent(): void
    {
        $term = $this->term();
        $this->validateIdentity($term);
        $this->get(route('eula.sign.step2', $term->token))->assertOk()->assertViewIs('public.eula.step2');
        $this->get(route('eula.sign.step3', $term->token))->assertRedirect(route('eula.sign.step2', $term->token));
        $this->postJson(route('eula.sign.finalize', $term->token), ['signature_data' => self::PNG])
            ->assertForbidden()->assertJson(['code' => 'eula_agreement_required']);
        $this->assertUnsigned($term);
    }

    public function test_accept_without_the_agreement_checkbox_does_not_authorize_signing(): void
    {
        $term = $this->term();
        $this->validateIdentity($term);
        $this->post(route('eula.sign.accept', $term->token), ['action' => 'accept'])
            ->assertRedirect(route('eula.sign.step2', $term->token))->assertSessionHasErrors('agree');
        $this->postJson(route('eula.sign.finalize', $term->token), ['signature_data' => self::PNG])->assertForbidden();
        $this->assertUnsigned($term);
    }

    public function test_the_complete_validated_and_agreed_flow_saves_one_signature_and_invalidates_the_token(): void
    {
        $term = $this->term();
        $token = $term->token;
        $this->validateIdentity($term);
        $this->agree($term);
        $this->get(route('eula.sign.step3', $token))->assertOk()->assertViewIs('public.eula.step3');
        $this->postJson(route('eula.sign.finalize', $token), ['signature_data' => self::PNG])
            ->assertOk()->assertJson(['success' => true]);
        $term->refresh();
        $this->assertNotNull($term->accepted_at);
        $this->assertNull($term->token);
        $this->assertNull($term->token_expires_at);
        Storage::assertExists('private_uploads/signatures/'.$term->signature_filename);
        $this->postJson(route('eula.sign.finalize', $token), ['signature_data' => self::PNG])->assertStatus(400);
        $this->assertSame(1, Actionlog::where('item_type', CheckoutAcceptance::class)
            ->where('item_id', $term->id)->where('action_type', 'eula_signature_completed')->count());
    }

    public static function recipients(): array
    {
        return [['same user'], ['different user']];
    }

    #[DataProvider('recipients')]
    public function test_validation_for_one_token_does_not_authorize_another(string $recipient): void
    {
        $first = $this->term();
        $second = $this->term($recipient === 'same user' ? $first->assignedTo : null);
        $this->validateIdentity($first);
        $this->agree($first);
        $this->get(route('eula.sign.step2', $second->token))->assertRedirect(route('eula.sign.step1', $second->token));
        $this->postJson(route('eula.sign.finalize', $second->token), ['signature_data' => self::PNG])->assertForbidden();
        $this->assertUnsigned($second);
    }

    public function test_a_new_session_must_validate_identity_again(): void
    {
        $term = $this->term();
        $this->validateIdentity($term);
        $this->agree($term);
        $this->app['session']->flush();
        $this->get(route('eula.sign.step3', $term->token))->assertRedirect(route('eula.sign.step1', $term->token));
        $this->assertUnsigned($term);
    }

    public static function lifetimeBoundaries(): array
    {
        return [['before expiry', 1799, true], ['at expiry', 1800, false]];
    }

    #[DataProvider('lifetimeBoundaries')]
    public function test_validation_has_an_absolute_expiry(string $label, int $seconds, bool $allowed): void
    {
        $this->freezeTime();
        $term = $this->term();
        $this->validateIdentity($term);
        $this->agree($term);
        $this->travel($seconds)->seconds();
        $response = $this->get(route('eula.sign.step3', $term->token));
        $allowed ? $response->assertOk() : $response->assertRedirect(route('eula.sign.step1', $term->token));
        $this->assertUnsigned($term);
    }

    public function test_rotated_token_requires_a_new_validation(): void
    {
        $term = $this->term();
        $this->validateIdentity($term);
        $this->agree($term);
        $term->generateToken();
        $this->get(route('eula.sign.step3', $term->token))->assertRedirect(route('eula.sign.step1', $term->token));
        $this->assertUnsigned($term);
    }

    public static function identityChanges(): array
    {
        return [['cpf'], ['name'], ['recipient']];
    }

    #[DataProvider('identityChanges')]
    public function test_identity_or_recipient_changes_invalidate_previous_validation(string $change): void
    {
        $term = $this->term();
        $this->validateIdentity($term);
        $this->agree($term);
        if ($change === 'recipient') {
            $term->assigned_to_id = User::factory()->create()->id;
            $term->save();
        } else {
            $term->assignedTo->update($change === 'cpf' ? ['employee_num' => '11144477735'] : ['first_name' => 'Outra']);
        }
        $this->get(route('eula.sign.step3', $term->token))->assertRedirect(route('eula.sign.step1', $term->token));
        $this->postJson(route('eula.sign.finalize', $term->token), ['signature_data' => self::PNG])->assertForbidden();
        $this->assertUnsigned($term);
    }

    public function test_failed_revalidation_revokes_previous_progress(): void
    {
        $term = $this->term();
        $this->validateIdentity($term);
        $this->agree($term);
        $this->post(route('eula.sign.validate', $term->token), [
            'cpf' => '11144477735', 'full_name' => self::NAME,
        ])->assertSessionHasErrors('cpf');
        $this->get(route('eula.sign.step3', $term->token))->assertRedirect(route('eula.sign.step1', $term->token));
        $this->assertUnsigned($term);
    }

    public function test_failed_revalidation_with_a_case_alias_revokes_the_canonical_token_progress(): void
    {
        $term = $this->term();
        $term->update(['token' => str_repeat('aB', 32)]);
        $alias = strtoupper($term->token);
        if (! CheckoutAcceptance::where('token', $alias)->exists()) {
            $this->markTestSkipped('Token aliases cannot select records with this database collation.');
        }
        $this->validateIdentity($term);
        $this->agree($term);
        $this->post(route('eula.sign.validate', $alias), [
            'cpf' => '11144477735', 'full_name' => self::NAME,
        ])->assertSessionHasErrors('cpf');
        $this->get(route('eula.sign.step3', $term->token))->assertRedirect(route('eula.sign.step1', $term->token));
        $this->postJson(route('eula.sign.finalize', $term->token), ['signature_data' => self::PNG])->assertForbidden();
        $this->assertUnsigned($term);
    }

    public static function unusableTerms(): array
    {
        return [['expired'], ['blocked'], ['accepted'], ['declined']];
    }

    #[DataProvider('unusableTerms')]
    public function test_unusable_terms_cannot_be_finalized_even_after_previous_validation(string $state): void
    {
        $term = $this->term();
        $this->validateIdentity($term);
        $this->agree($term);
        $term->fill(match ($state) {
            'expired' => ['token_expires_at' => now()->subSecond()],
            'blocked' => ['blocked_until' => now()->addMinutes(5)],
            'accepted' => ['accepted_at' => now()],
            'declined' => ['declined_at' => now()],
        })->save();
        $this->postJson(route('eula.sign.finalize', $term->token), ['signature_data' => self::PNG])->assertStatus(400);
        $this->assertNull($term->fresh()->signature_filename);
        $this->assertSame([], Storage::allFiles('private_uploads/signatures'));
    }

    public function test_decline_is_allowed_only_after_validation_and_invalidates_the_link(): void
    {
        $term = $this->term();
        $this->validateIdentity($term);
        $this->post(route('eula.sign.accept', $term->token), ['action' => 'decline'])
            ->assertOk()->assertSee('Termo recusado com sucesso.')
            ->assertDontSee('Termo de Aceite Assinado Digitalmente');
        $this->assertNotNull($term->fresh()->declined_at);
        $this->assertNull($term->fresh()->accepted_at);
        $this->assertNull($term->fresh()->token);
    }
}
