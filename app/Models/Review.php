<?php

namespace App\Models;

use App\Enums\ReviewVerdict;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'answer_id',
        'verdict',
        'teacher_file_id',
        'prompt_message_id',
        'sent_at',
    ];

    protected $casts = [
        'verdict' => ReviewVerdict::class,
        'sent_at' => 'datetime',
    ];

    public function answer()
    {
        return $this->belongsTo(Answer::class);
    }

    public function isDraft(): bool
    {
        return $this->sent_at === null;
    }
}
