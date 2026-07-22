<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'question',
        'description',
        'result_visibility',
        'question_name_mode',
        'questions_enabled',
        'allows_multiple_choice',
        'is_active',
        'creator_ip',
        'last_activity_at',
    ];

    protected $casts = [
        'allows_multiple_choice' => 'boolean',
        'questions_enabled' => 'boolean',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    /**
     * manage_token ist der "Admin-Schlüssel" der Umfrage und darf niemals
     * über die Public-Routen ausgeliefert werden. In allen Public-Kontexten
     * (PublicPollController) muss das Model daher immer mit
     * ->makeHidden('manage_token') o.ä. bzw. über ein explizites Resource-Array
     * serialisiert werden, statt das Model direkt an Inertia zu übergeben.
     */
    protected $hidden = [];

    protected static function booted(): void
    {
        static::creating(function (Poll $poll) {
            $poll->public_token ??= self::generateUniqueToken('public_token', 12);
            $poll->manage_token ??= self::generateUniqueToken('manage_token', 64);
            $poll->last_activity_at ??= now();
        });
    }

    protected static function generateUniqueToken(string $column, int $length): string
    {
        do {
            $token = Str::random($length);
        } while (self::where($column, $token)->exists());

        return $token;
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function options()
    {
        return $this->hasMany(PollOption::class)->orderBy('sort_order');
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->latest();
    }

    /**
     * Wird bei jedem neuen Vote / jeder neuen Frage aufgerufen (Abschnitt 10:
     * 90-Tage-Löschkonzept basiert auf last_activity_at).
     */
    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }
}
