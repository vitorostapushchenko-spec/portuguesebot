<?php

namespace App\Telegram\Handlers;

use App\Enums\ReviewVerdict;
use App\Models\Answer;
use App\Models\Review;
use App\Services\ReviewFlow;
use App\Support\Teacher;
use App\Support\TeacherMessages;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * The teacher's Good/Not yet/Skip/Send/Start over buttons (spec §3.4).
 *
 * Good sends "Молодец!" to the learner at once. Not yet asks for a spoken tip, then Skip/Send/Start over.
 */
class TeacherReviewCallbackHandler
{
    public function verdict(Nutgram $bot, string $verdict, string $answerId): void
    {
        $bot->answerCallbackQuery();

        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $answer = Answer::find($answerId);

        if ($answer === null) {
            return;
        }

        $flow = app(ReviewFlow::class);

        if ($verdict === 'good') {
            if ($flow->alreadySentGood($answer)) {
                $bot->sendMessage(TeacherMessages::alreadySentGood($answer->learner->displayName()));

                return;
            }

            $flow->sendGood($bot, $answer);

            return;
        }

        $review = Review::firstOrCreate(
            ['answer_id' => $answer->id, 'sent_at' => null],
            ['verdict' => ReviewVerdict::NotYet]
        );
        $review->update(['verdict' => ReviewVerdict::NotYet]);

        $flow->askForRecording($bot, $review);
    }

    public function skip(Nutgram $bot, string $reviewId): void
    {
        $bot->answerCallbackQuery();

        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $review = Review::whereNull('sent_at')->find($reviewId);

        if ($review === null) {
            return;
        }

        app(ReviewFlow::class)->confirmStep($bot, $review);
    }

    public function send(Nutgram $bot, string $reviewId): void
    {
        $bot->answerCallbackQuery();

        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $review = Review::whereNull('sent_at')->find($reviewId);

        if ($review === null) {
            return;
        }

        app(ReviewFlow::class)->deliver($bot, $review);
    }

    public function redo(Nutgram $bot, string $reviewId): void
    {
        $bot->answerCallbackQuery();

        if (! Teacher::isTeacher($bot)) {
            return;
        }

        $review = Review::whereNull('sent_at')->find($reviewId);

        if ($review === null) {
            return;
        }

        $answerId = $review->answer_id;
        $review->delete();

        $bot->sendMessage(
            'Choose again:',
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('✅ Good', callback_data: "rev:good:{$answerId}"),
                InlineKeyboardButton::make('❌ Not yet', callback_data: "rev:notyet:{$answerId}"),
            ),
        );
    }
}
