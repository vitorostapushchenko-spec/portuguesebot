<?php

namespace App\Telegram\Commands;

use App\Models\Learner;
use App\Services\LessonScheduler;
use App\Support\BotMessages;
use Carbon\CarbonImmutable;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

/**
 * §3.6: sends the next lesson right away, if the current one has been answered.
 */
class NextCommand extends Command
{
    protected string $command = 'next';

    protected ?string $description = 'Pedir a próxima lição agora.';

    public function handle(Nutgram $bot): void
    {
        $learner = Learner::where('telegram_id', $bot->userId())->first();

        if ($learner === null || $learner->lesson_time === null) {
            return;
        }

        $result = (new LessonScheduler($bot))->sendNext($learner, CarbonImmutable::now(config('course.timezone')));

        match ($result) {
            'refused' => $bot->sendMessage(BotMessages::nextRefused()),
            'no_lessons_left' => $bot->sendMessage(BotMessages::nextNoLessonsLeft()),
            default => null,
        };
    }
}
