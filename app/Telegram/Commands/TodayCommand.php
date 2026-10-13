<?php

namespace App\Telegram\Commands;

use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Services\LessonDelivery;
use App\Support\BotMessages;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

/**
 * §3.9: sends the current lesson again (whatever the learner's open/last lesson is), with its recording.
 */
class TodayCommand extends Command
{
    protected string $command = 'today';

    protected ?string $description = 'Ver a lição atual de novo.';

    public function handle(Nutgram $bot): void
    {
        $learner = Learner::where('telegram_id', $bot->userId())->first();

        if ($learner === null || $learner->status !== LearnerStatus::Active) {
            return;
        }

        $send = $learner->currentSend();

        if ($send === null) {
            $nextTime = $learner->lesson_time ? substr((string) $learner->lesson_time, 0, 5) : null;
            $bot->sendMessage(BotMessages::noLessonToAnswer($nextTime));

            return;
        }

        app(LessonDelivery::class)->send($bot, $send->lesson, $learner->telegram_id);
    }
}
