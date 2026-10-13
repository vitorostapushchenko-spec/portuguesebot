<?php

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\SendStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'learner_id',
        'lesson_id',
        'delivery_type',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'delivery_type' => DeliveryType::class,
        'status' => SendStatus::class,
        'sent_at' => 'datetime',
    ];

    public function learner()
    {
        return $this->belongsTo(Learner::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
