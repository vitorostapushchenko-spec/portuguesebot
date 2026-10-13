<?php

namespace App\Telegram\Handlers;

use App\Enums\LearnerStatus;
use App\Enums\SendStatus;
use App\Models\Learner;
use App\Services\TimeParser;
use App\Services\TimeSetter;
use App\Support\BotMessages;
use App\Support\MainKeyboard;
use App\Support\Teacher;
use App\Support\TimeKeyboard;
use App\Telegram\Commands\NextCommand;
use App\Telegram\Commands\ProgressCommand;
use SergiX44\Nutgram\Nutgram;

/**
 * Catch-all for plain text that isn't a command (§3.1 typed times, plus the
 * §3.3 reminder when a voice answer was expected but text arrived instead).
 */
class TypedTextHandler
{
    public function handle(Nutgram $bot): void
    {
        if (Teacher::isTeacher($bot)) {
            return;
        }

        $text = $bot->message()?->text ?? '';
        $learner = Learner::where('telegram_id', $bot->userId())->first();

        if ($learner === null) {
            return;
        }

        // The persistent keyboard's two buttons act exactly like /next and
        // /progress, and must never fall through to the "send a voice
        // message" reminder below.
        if ($text === MainKeyboard::NEXT_LABEL) {
            app(NextCommand::class)->handle($bot);

            return;
        }

        if ($text === MainKeyboard::PROGRESS_LABEL) {
            app(ProgressCommand::class)->handle($bot);

            return;
        }

        $parsed = TimeParser::parse($text);

        if ($parsed !== null) {
            app(TimeSetter::class)->apply($learner, $parsed['hour'], $parsed['minute'], $bot);

            return;
        }

        if ($learner->status === LearnerStatus::SettingUp) {
            $bot->sendMessage(BotMessages::timeNotUnderstood(), reply_markup: TimeKeyboard::build());

            return;
        }

        $current = $learner->currentSend();

        if ($learner->status === LearnerStatus::Active && $current?->status === SendStatus::Open) {
            $bot->sendMessage(BotMessages::notAVoiceMessage());
        }
    }
}
