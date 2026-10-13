<?php

namespace App\Support;

use App\Models\Learner;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

/**
 * The persistent bottom keyboard offering "Next lesson" / "My progress" as an
 * alternative to typing /next and /progress.
 */
class MainKeyboard
{
    public const NEXT_LABEL = '⏭ Следующий урок';

    public const PROGRESS_LABEL = '📊 Мой прогресс';

    public static function build(): ReplyKeyboardMarkup
    {
        return ReplyKeyboardMarkup::make(resize_keyboard: true, is_persistent: true)
            ->addRow(
                KeyboardButton::make(self::NEXT_LABEL),
                KeyboardButton::make(self::PROGRESS_LABEL),
            );
    }

    /**
     * Returns the keyboard markup only the first time it's needed for this
     * learner, marking it sent so later calls don't resend it.
     */
    public static function attachOnceFor(Learner $learner): ?ReplyKeyboardMarkup
    {
        if ($learner->keyboard_sent_at !== null) {
            return null;
        }

        $learner->update(['keyboard_sent_at' => now()]);

        return self::build();
    }
}
