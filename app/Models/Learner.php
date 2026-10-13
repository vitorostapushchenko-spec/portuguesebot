<?php

namespace App\Models;

use App\Enums\LearnerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Learner extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'lesson_time',
        'current_lesson_number',
        'status',
        'consecutive_unanswered',
        'course_started_at',
        'inactivity_notified_at',
        'keyboard_sent_at',
    ];

    protected $casts = [
        'status' => LearnerStatus::class,
        'course_started_at' => 'datetime',
        'inactivity_notified_at' => 'datetime',
        'keyboard_sent_at' => 'datetime',
    ];

    // Eloquent doesn't read DB column defaults back into a freshly created
    // instance, so these are set here too — matters right after firstOrCreate().
    protected $attributes = [
        'current_lesson_number' => 1,
        'status' => 'setting_up',
        'consecutive_unanswered' => 0,
    ];

    public function sends()
    {
        return $this->hasMany(LessonSend::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function currentSend(): ?LessonSend
    {
        return $this->sends()->latest('sent_at')->first();
    }

    public function displayName(): string
    {
        return $this->first_name ?: ($this->username ? '@'.$this->username : (string) $this->telegram_id);
    }
}
