<?php

namespace App\Telegram\Commands;

use App\Models\Learner;
use App\Services\ProgressCalculator;
use App\Support\BotMessages;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

/**
 * §3.5/§3.9: level bar picture, level, lessons answered and next lesson time.
 */
class ProgressCommand extends Command
{
    protected string $command = 'progress';

    protected ?string $description = 'Ver seu nível e progresso.';

    public function handle(Nutgram $bot): void
    {
        $learner = Learner::where('telegram_id', $bot->userId())->first();

        if ($learner === null) {
            return;
        }

        $calculator = app(ProgressCalculator::class);
        $level = $calculator->level($learner);
        $answered = $calculator->answeredLessonsCount($learner);
        $nextTime = $learner->lesson_time ? substr((string) $learner->lesson_time, 0, 5) : null;

        $bot->sendMessage(
            BotMessages::progress($level, $answered, config('course.total_lessons'), $nextTime)
        );
    }
}
