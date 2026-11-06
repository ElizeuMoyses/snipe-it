<?php

namespace App\Services;

use App\Models\CheckoutAcceptance;
use Illuminate\Contracts\Session\Session;

class PublicEulaProgress
{
    public function __construct(private readonly Session $session)
    {
    }

    public function validateIdentity(CheckoutAcceptance $acceptance): void
    {
        $this->session->put($this->key($acceptance->token), [
            'acceptance_id' => $acceptance->id,
            'assigned_to_id' => $acceptance->assigned_to_id,
            'identity' => $this->identityFingerprint($acceptance),
            'expires_at' => now()->addMinutes(max(1, (int) config('eula.validation_session_minutes', 30)))->getTimestamp(),
            'agreed' => false,
        ]);
    }

    public function identityValidated(CheckoutAcceptance $acceptance): bool
    {
        $state = $this->session->get($this->key($acceptance->token));
        $valid = $acceptance->isPending()
            && $acceptance->isTokenValid()
            && $acceptance->assignedTo
            && is_array($state)
            && ($state['acceptance_id'] ?? null) === $acceptance->id
            && ($state['assigned_to_id'] ?? null) === $acceptance->assigned_to_id
            && is_int($state['expires_at'] ?? null)
            && $state['expires_at'] > now()->getTimestamp()
            && is_string($state['identity'] ?? null)
            && hash_equals($this->identityFingerprint($acceptance), $state['identity']);

        if (! $valid) {
            $this->forget($acceptance->token);
        }

        return (bool) $valid;
    }

    public function acceptAgreement(CheckoutAcceptance $acceptance): void
    {
        if ($this->identityValidated($acceptance)) {
            $this->session->put($this->key($acceptance->token).'.agreed', true);
        }
    }

    public function agreementAccepted(CheckoutAcceptance $acceptance): bool
    {
        return $this->identityValidated($acceptance)
            && $this->session->get($this->key($acceptance->token).'.agreed') === true;
    }

    public function clearAgreement(CheckoutAcceptance $acceptance): void
    {
        $this->session->forget($this->key($acceptance->token).'.agreed');
    }

    public function forget(string $token): void
    {
        $this->session->forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'public_eula_progress.'.hash('sha256', $token);
    }

    private function identityFingerprint(CheckoutAcceptance $acceptance): string
    {
        $user = $acceptance->assignedTo;

        return hash_hmac('sha256', json_encode([
            $user->id, $user->employee_num, $user->first_name, $user->last_name,
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
