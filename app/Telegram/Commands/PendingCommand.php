<?php

namespace App\Telegram\Commands;

use App\Models\Answer;
use App\Services\ProgressCalculator;
use App\Support\Teacher;
use App\Support\TeacherMessages;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * §3.9 (teacher-only): answers waiting for review, oldest first.
 */
class PendingCommand extends Command
{
    protected string $command = 'pending';

    public function handle(Nutgram $bot): void
    {
        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $answers = Answer::with(['learner', 'lessonSend.lesson'])
            ->where('review_status', 'waiting')
            ->oldest('received_at')
            ->get();

        if ($answers->isEmpty()) {
            $bot->sendMessage(TeacherMessages::noPendingAnswers());

            return;
        }

        $level = app(ProgressCalculator::class);

        foreach ($answers as $answer) {
            $learner = $answer->learner;
            $lesson = $answer->lessonSend->lesson;

            $bot->sendMessage(
                "Lesson {$lesson->order} · {$learner->displayName()} · attempt {$answer->attempt_number}",
                reply_markup: InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make('Open', callback_data: "pending:open:{$answer->id}")
                ),
            );
        }
    }
}
