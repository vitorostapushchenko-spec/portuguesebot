<?php

namespace App\Support;

use SergiX44\Nutgram\Nutgram;

class Teacher
{
    public static function isTeacher(Nutgram $bot): bool
    {
        $teacherChatId = config('course.teacher_chat_id');

        return $teacherChatId !== null && (string) $bot->chatId() === (string) $teacherChatId;
    }
}
