<?php

namespace App\Telegram\Handlers;

use App\Enums\AnswerReviewStatus;
use App\Models\Answer;
use App\Services\ProgressCalculator;
use App\Support\Teacher;
use App\Support\TeacherMessages;
use App\Telegram\Commands\PendingCommand;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class PendingCallbackHandler
{
    public function list(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        if (! Teacher::isTeacher($bot)) {
            return;
        }

        app(PendingCommand::class)->handle($bot);
    }

    public function open(Nutgram $bot, string $answerId): void
    {
        $bot->answerCallbackQuery();

        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $answer = Answer::with(['learner', 'lessonSend.lesson'])->find($answerId);

        if ($answer === null || $answer->review_status !== AnswerReviewStatus::Waiting) {
            return;
        }

        $learner = $answer->learner;
        $lesson = $answer->lessonSend->lesson;
        $level = app(ProgressCalculator::class)->level($learner);

        $bot->sendMessage(
            TeacherMessages::newAnswerToReview(
                $learner->displayName(),
                $learner->username,
                $lesson->order,
                config('course.total_lessons'),
                $lesson->phrase_pt,
                $answer->attempt_number,
                $level,
            ),
        );

        $bot->sendVoice(
            $answer->telegram_file_id,
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('✅ Good', callback_data: "rev:good:{$answer->id}"),
                InlineKeyboardButton::make('❌ Not yet', callback_data: "rev:notyet:{$answer->id}"),
            ),
        );
    }
}
