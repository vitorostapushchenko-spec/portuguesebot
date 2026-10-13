<?php

namespace App\Services;

use App\Models\Learner;
use App\Support\BotMessages;

/**
 * Renders a learner's current level-bar picture to a temp file, ready to
 * upload. Used both right after an answer and by the teacher's on-demand
 * /level check.
 */
class LevelBarRenderer
{
    public function __construct(
        private ProgressCalculator $calculator,
        private LevelBarImage $image,
    ) {
    }

    /**
     * @return array{level: int, path: string}
     */
    public function renderForLearner(Learner $learner): array
    {
        $answered = $this->calculator->answeredLessonsCount($learner);
        $level = $this->calculator->level($learner);
        $band = $this->calculator->band($level);
        $totalLessons = config('course.total_lessons');

        $png = $this->image->generate(
            $level,
            $band,
            $this->calculator->colorHex($band),
            BotMessages::levelMessage($level),
            $answered,
            $totalLessons,
        );

        $path = tempnam(sys_get_temp_dir(), 'level').'.png';
        file_put_contents($path, $png);

        return ['level' => $level, 'path' => $path];
    }
}
