<?php

namespace App\Models;

use App\Support\BotMessages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'order',
        'category',
        'phrase_pt',
        'phrase_ru',
        'phrase_en',
        'pronunciation',
        'has_variant',
        'image_path',
        'audio_file_id',
        'audio_kind',
    ];

    protected $casts = [
        'has_variant' => 'boolean',
    ];

    public function sends()
    {
        return $this->hasMany(LessonSend::class);
    }

    /** True when the teacher has recorded this phrase (see /audio); the recording goes out with the lesson. */
    public function hasAudio(): bool
    {
        return $this->audio_file_id !== null && $this->audio_file_id !== '';
    }

    /**
     * The lesson content block: phrase, translations and pronunciation, each
     * flagged by country, then the task line (spec §3.2).
     */
    public function caption(): string
    {
        return "🇧🇷 <b>{$this->phrase_pt}</b>\n".
            "🇷🇺 {$this->phrase_ru}\n".
            "🇬🇧 {$this->phrase_en}\n".
            "🎤 {$this->pronunciation}\n\n".
            BotMessages::taskLine($this->has_variant);
    }
}
