<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class CheckoutAcceptance extends Model
{
    use HasFactory, SoftDeletes, Notifiable;

    protected $fillable = [
        'checkoutable_id',
        'checkoutable_type',
        'assigned_to_id',
        'signature_filename',
        'accepted_at',
        'declined_at',
        'stored_eula',
        'stored_eula_file',
        'note',
        'token',
        'token_expires_at',
        'failed_attempts',
        'blocked_until',
        'signature_latitude',
        'signature_longitude',
        'signature_device_type',
        'signature_ip'
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'alert_on_response_id' => 'integer',
        'token_expires_at' => 'datetime',
        'blocked_until' => 'datetime',
    ];

    /**
     * Get the mail recipient from the config
     *
     * @return mixed|string|null
     */
    public function routeNotificationForMail()
    {
        // At this point the endpoint is the same for everything.
        //  In the future this may want to be adapted for individual notifications.
        $recipients_string = explode(',', Setting::getSettings()->alert_email);
        $recipients = array_map('trim', $recipients_string);

        return array_filter($recipients);
    }
    public function getCheckoutableItemTypeAttribute(): string
    {
        $type = $this->checkoutable_type;

        return match ($type) {
            Asset::class       => trans('general.asset'),
            LicenseSeat::class => trans('general.license'),
            Accessory::class   => trans('general.accessory'),
            Component::class   => trans('general.component'),
            Consumable::class  => trans('general.consumable'),
            default            => class_basename($type),
        };
    }
    /**
     * The resource that was is out
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function checkoutable()
    {
        return $this->morphTo();
    }

    /**
     * The user that the checkoutable was checked out to
     *
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Is this checkout acceptance pending?
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->accepted_at == null && $this->declined_at == null;
    }

    /**
     * Was the checkoutable checked out to this user?
     *
     * @param  User $user
     * @return bool
     */
    public function isCheckedOutTo(User $user)
    {
        return $this->assignedTo?->is($user);
    }

    /**
     * Add a record to the checkout_acceptance table ONLY.
     * Do not add stuff here that doesn't have a corresponding column in the
     * checkout_acceptances table or you'll get an error.
     *
     * @param string $signature_filename
     */
    public function accept($signature_filename, $eula = null, $filename = null, $note = null)
    {
        $this->accepted_at = now();
        $this->signature_filename = $signature_filename;
        $this->stored_eula = $eula;
        $this->stored_eula_file = $filename;
        $this->note = $note;
        $this->save();

        /**
         * Update state for the checked out item
         */
        $this->checkoutable->acceptedCheckout($this->assignedTo, $signature_filename, $filename);
    }

    /**
     * Decline the checkout acceptance
     *
     * @param string $signature_filename
     */
    public function decline($signature_filename, $note = null)
    {
        $this->declined_at = now();
        $this->note = $note;
        $this->signature_filename = $signature_filename;
        $this->save();

        /**
         * Update state for the checked out item
         */
        if ($this->checkoutable instanceof Accessory) {
            $this->checkoutable->declinedCheckout($this->assignedTo, $signature_filename, (int) ($this->qty ?? 1));
        } else {
            $this->checkoutable->declinedCheckout($this->assignedTo, $signature_filename);
        }
    }

    /**
     * Filter checkout acceptences by the user
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @param  User                                 $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser(Builder $query, User $user)
    {
        return $query->where('assigned_to_id', $user->id);
    }

    /**
     * Filter to only get pending acceptances
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending(Builder $query)
    {
        return $query->whereNull('accepted_at')->whereNull('declined_at');
    }

    /**
     * Filter to only get completed signatures (accepted with signature file)
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted(Builder $query)
    {
        return $query->whereNotNull('accepted_at')
                     ->whereNotNull('signature_filename');
    }

    /**
     * Filter to only get signatures with geolocation data
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithGeolocation(Builder $query)
    {
        return $query->whereNotNull('signature_latitude')
                     ->whereNotNull('signature_longitude');
    }

    /**
     * Filter signatures by device type
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @param  string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDeviceType(Builder $query, $type)
    {
        return $query->where('signature_device_type', $type);
    }

    /**
     * Filter signatures by date range
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @param  string|null $from
     * @param  string|null $to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange(Builder $query, $from = null, $to = null)
    {
        if ($from) {
            $query->where('accepted_at', '>=', $from);
        }
        
        if ($to) {
            $query->where('accepted_at', '<=', $to);
        }
        
        return $query;
    }

    /**
     * Generate a secure token for public EULA signing
     *
     * @return string
     */
    public function generateToken(): string
    {
        $this->token = Str::random(64);
        $this->token_expires_at = now()->addDays(config('eula.token_expiry_days', 30));
        $this->save();
        return $this->token;
    }

    /**
     * Check if the token is valid and not expired or blocked
     *
     * @return bool
     */
    public function isTokenValid(): bool
    {
        return $this->token && 
               $this->token_expires_at && 
               $this->token_expires_at->isFuture() &&
               !$this->isBlocked();
    }

    /**
     * Get the number of days this acceptance has been pending
     *
     * @return int
     */
    public function getDaysPending(): int
    {
        return $this->created_at ? (int) $this->created_at->diffInDays(now()) : 0;
    }

    /**
     * Get the CSS class for priority based on days pending
     *
     * @return string
     */
    public function getPriorityClass(): string
    {
        $days = $this->getDaysPending();
        
        if ($days > 30) return 'danger-high';
        if ($days > 14) return 'danger';
        if ($days > 7) return 'warning';
        return '';
    }

    /**
     * Check if this acceptance is temporarily blocked due to failed attempts
     *
     * @return bool
     */
    public function isBlocked(): bool
    {
        return $this->blocked_until && $this->blocked_until->isFuture();
    }

    /**
     * Increment failed attempts and block if threshold is reached
     *
     * @return void
     */
    public function incrementFailedAttempts(): void
    {
        $this->failed_attempts++;
        
        $maxAttempts = config('eula.max_failed_attempts', 5);
        $blockDuration = config('eula.block_duration_minutes', 30);
        
        if ($this->failed_attempts >= $maxAttempts) {
            $this->blocked_until = now()->addMinutes($blockDuration);
        }
        
        $this->save();
    }
}
