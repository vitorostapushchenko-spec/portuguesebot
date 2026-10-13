<?php

namespace App\Telegram\Commands;

use App\Models\Learner;
use App\Services\LevelBarRenderer;
use App\Support\Teacher;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

/**
 * Teacher-only: check a learner's current level bar on demand, without
 * waiting for their next answer.
 */
class LevelCommand extends Command
{
    protected string $command = 'level';

    public function handle(Nutgram $bot): void
    {
        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $learners = Learner::all();

        if ($learners->isEmpty()) {
            $bot->sendMessage('No learners yet.');

            return;
        }

        $renderer = app(LevelBarRenderer::class);

        foreach ($learners as $learner) {
            ['level' => $level, 'path' => $path] = $renderer->renderForLearner($learner);
            $caption = "{$learner->displayName()} — lesson {$learner->current_lesson_number}/".config('course.total_lessons');

            if ($path === null) {
                $bot->sendMessage("{$caption}\nNo answers yet.");

                continue;
            }

            $bot->sendPhoto(InputFile::make($path), caption: $caption);
            @unlink($path);
        }
    }
}
