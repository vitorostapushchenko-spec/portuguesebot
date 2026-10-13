<?php

namespace App\Models;

use App\Enums\AnswerReviewStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'learner_id',
        'lesson_send_id',
        'attempt_number',
        'telegram_file_id',
        'telegram_message_id',
        'received_at',
        'review_status',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'review_status' => AnswerReviewStatus::class,
    ];

    public function learner()
    {
        return $this->belongsTo(Learner::class);
    }

    public function lessonSend()
    {
        return $this->belongsTo(LessonSend::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
