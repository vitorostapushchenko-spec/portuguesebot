<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Which lesson the teacher is recording right now: /audio (or its button) sets it, and the teacher's next
 * voice message is saved as that lesson's recording. It is forgotten after half an hour, so a voice message
 * sent much later is not mistaken for a lesson recording.
 */
class LessonAudioRecording
{
    private const KEY = 'teacher:lesson-audio-recording';

    private const MINUTES = 30;

    public static function start(int $lessonNumber): void
    {
        Cache::put(self::KEY, $lessonNumber, now()->addMinutes(self::MINUTES));
    }

    public static function pending(): ?int
    {
        $value = Cache::get(self::KEY);

        return $value === null ? null : (int) $value;
    }

    public static function clear(): void
    {
        Cache::forget(self::KEY);
    }
}
