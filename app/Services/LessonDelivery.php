<?php

namespace App\Services;

use App\Models\Lesson;
use App\Support\BotMessages;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

/**
 * Sends one lesson to a learner: the card (illustration with the phrase, translations and pronunciation, or
 * just the text when there is no illustration) and then, when the teacher has recorded the phrase, the
 * recording, so the learner hears how it sounds before answering. Used by the daily lesson, /next and /today.
 */
class LessonDelivery
{
    public function send(Nutgram $bot, Lesson $lesson, int|string $chatId): void
    {
        $caption = $lesson->caption();
        $imagePath = $lesson->image_path ? Storage::disk('public')->path($lesson->image_path) : null;

        if ($imagePath && File::exists($imagePath)) {
            $bot->sendPhoto(InputFile::make($imagePath), chat_id: $chatId, caption: $caption, parse_mode: 'HTML');
        } else {
            $bot->sendMessage($caption, chat_id: $chatId, parse_mode: 'HTML');
        }

        $this->sendRecording($bot, $lesson, $chatId);
    }

    /**
     * The recording is an extra: if Telegram refuses it (a file id that no longer works, for instance), the
     * lesson has still been delivered, so the error is logged and nothing else stops.
     */
    private function sendRecording(Nutgram $bot, Lesson $lesson, int|string $chatId): void
    {
        if (! $lesson->hasAudio()) {
            return;
        }

        try {
            if ($lesson->audio_kind === 'audio') {
                $bot->sendAudio($lesson->audio_file_id, chat_id: $chatId, caption: BotMessages::lessonAudioCaption());
            } else {
                $bot->sendVoice($lesson->audio_file_id, chat_id: $chatId, caption: BotMessages::lessonAudioCaption());
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
