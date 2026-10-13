<?php

namespace App\Telegram\Commands;

use App\Models\Learner;
use App\Services\ProgressCalculator;
use App\Support\Teacher;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

/**
 * §3.9 (teacher-only): every learner, their lesson, level and last answer date.
 */
class LearnersCommand extends Command
{
    protected string $command = 'learners';

    public function handle(Nutgram $bot): void
    {
        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $learners = Learner::orderBy('first_name')->get();

        if ($learners->isEmpty()) {
            $bot->sendMessage('No learners yet.');

            return;
        }

        $calculator = app(ProgressCalculator::class);
        $lines = [];

        foreach ($learners as $learner) {
            $level = $calculator->level($learner);
            $lastAnswer = $learner->answers()->latest('received_at')->first();
            $lastAnswerText = $lastAnswer ? $lastAnswer->received_at->format('Y-m-d H:i') : 'never';

            $lines[] = "{$learner->displayName()} — lesson {$learner->current_lesson_number}/".
                config('course.total_lessons')." · {$level}% · {$learner->status->value} · last answer: {$lastAnswerText}";
        }

        $bot->sendMessage(implode("\n", $lines));
    }
}
