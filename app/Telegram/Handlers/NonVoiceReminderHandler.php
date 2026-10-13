<?php

namespace App\Telegram\Handlers;

use App\Enums\LearnerStatus;
use App\Enums\SendStatus;
use App\Models\Learner;
use App\Support\BotMessages;
use App\Support\Teacher;
use SergiX44\Nutgram\Nutgram;

/**
 * §3.3: a photo/sticker/video/document arrives instead of the expected voice
 * message — nothing is forwarded, the bot just asks for a voice message.
 */
class NonVoiceReminderHandler
{
    public function handle(Nutgram $bot): void
    {
        if (Teacher::isTeacher($bot)) {
            return;
        }

        $learner = Learner::where('telegram_id', $bot->userId())->first();

        if ($learner === null || $learner->status !== LearnerStatus::Active) {
            return;
        }

        $current = $learner->currentSend();

        if ($current?->status === SendStatus::Open) {
            $bot->sendMessage(BotMessages::notAVoiceMessage());
        }
    }
}
