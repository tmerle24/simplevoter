<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = ['poll_id', 'content', 'author_name', 'author_token'];

    public function poll()
    {
        return $this->belongsTo(Poll::class);
    }
}
