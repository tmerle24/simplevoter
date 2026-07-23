<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = ['name', 'public_token', 'manage_token', 'active_poll_id'];

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->public_token ??= self::generateUniqueToken('public_token', 12);
            $event->manage_token ??= self::generateUniqueToken('manage_token', 64);
        });
    }

    protected static function generateUniqueToken(string $column, int $length): string
    {
        do {
            $token = Str::random($length);
        } while (self::where($column, $token)->exists());

        return $token;
    }

    public function polls()
    {
        return $this->hasMany(Poll::class)->orderBy('sort_order')->orderBy('created_at');
    }

    public function activePoll()
    {
        return $this->belongsTo(Poll::class, 'active_poll_id');
    }
}
