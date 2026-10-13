<?php

namespace App\Support;

use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * One button per half hour, 06:00–23:30, 6 per row.
 */
class TimeKeyboard
{
    public static function build(): InlineKeyboardMarkup
    {
        $range = config('course.lesson_time_choices');
        $keyboard = InlineKeyboardMarkup::make();

        $slots = [];
        foreach (range($range['start'], $range['end']) as $hour) {
            $slots[] = sprintf('%02d:00', $hour);
            $slots[] = sprintf('%02d:30', $hour);
        }

        foreach (array_chunk($slots, 6) as $row) {
            $keyboard->addRow(...array_map(
                fn (string $time) => InlineKeyboardButton::make($time, callback_data: "time:{$time}"),
                $row,
            ));
        }

        return $keyboard;
    }
}
