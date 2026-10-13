<?php

namespace App\Services;

use App\Enums\SendStatus;
use App\Models\Learner;

/**
 * Progress level = course advancement, not accuracy: each of the 50 lessons
 * counts once it's been answered with at least one voice message, regardless
 * of the teacher's Good/Not yet verdict or how many attempts it took.
 * level = (distinct lessons answered) × 2, so 0 lessons = 0% and 50 = 100%.
 */
class ProgressCalculator
{
    public function answeredLessonsCount(Learner $learner): int
    {
        return $learner->sends()
            ->where('status', SendStatus::Answered)
            ->distinct('lesson_id')
            ->count('lesson_id');
    }

    public function level(Learner $learner): int
    {
        return min(100, $this->answeredLessonsCount($learner) * 2);
    }

    /**
     * level_1 (0–25%) through level_4 (76–100%) — gradual yellow to green, no red.
     */
    public function band(int $level): string
    {
        return match (true) {
            $level >= 76 => 'level_4',
            $level >= 51 => 'level_3',
            $level >= 26 => 'level_2',
            default => 'level_1',
        };
    }

    public function colorHex(string $band): string
    {
        return match ($band) {
            'level_4' => '#3FAE6A',
            'level_3' => '#8DBA34',
            'level_2' => '#C8BE24',
            default => '#EDB21D',
        };
    }
}
