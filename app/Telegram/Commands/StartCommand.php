<?php

namespace App\Telegram\Commands;

use App\Enums\LearnerStatus;
use App\Models\Learner;
use App\Services\ProgressCalculator;
use App\Support\BotMessages;
use App\Support\MainKeyboard;
use App\Support\TimeKeyboard;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;

/**
 * §3.1 (first contact) and §3.9 (Start pressed again / resuming from pause or block).
 */
class StartCommand extends Command
{
    protected string $command = 'start';

    protected ?string $description = 'Começar ou ver o andamento do curso.';

    public function handle(Nutgram $bot): void
    {
        $learner = Learner::firstOrCreate(
            ['telegram_id' => $bot->userId()],
            [
                'username' => $bot->user()?->username,
                'first_name' => $bot->user()?->first_name,
                'status' => LearnerStatus::SettingUp,
            ]
        );

        $learner->update([
            'username' => $bot->user()?->username,
            'first_name' => $bot->user()?->first_name,
        ]);

        if ($learner->lesson_time === null) {
            $bot->sendMessage(BotMessages::welcomeAndTimeQuestion(), reply_markup: TimeKeyboard::build());

            return;
        }

        if (in_array($learner->status, [LearnerStatus::Paused, LearnerStatus::PausedInactive, LearnerStatus::Blocked], true)) {
            $learner->update(['status' => LearnerStatus::Active, 'consecutive_unanswered' => 0]);
        }

        $level = app(ProgressCalculator::class)->level($learner);
        $time = substr((string) $learner->lesson_time, 0, 5);

        $bot->sendMessage(
            BotMessages::startPressedAgain($time, $learner->current_lesson_number, config('course.total_lessons'), $level),
            reply_markup: MainKeyboard::attachOnceFor($learner),
        );
    }
}
