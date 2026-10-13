<?php

namespace App\Services;

/**
 * Parses the free-text lesson time the learner can type instead of pressing a
 * button (spec §3.1). Accepted: 7, 07:00, 7:30, 19h, 19h30.
 */
class TimeParser
{
    /**
     * @return array{hour: int, minute: int}|null null when the input doesn't match any accepted format or is out of range.
     */
    public static function parse(string $input): ?array
    {
        $input = trim($input);

        $patterns = [
            '/^(\d{1,2}):(\d{2})$/',   // 07:00, 7:30
            '/^(\d{1,2})h(\d{2})$/',   // 19h30
            '/^(\d{1,2})h$/',          // 19h
            '/^(\d{1,2})$/',           // 7
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                $hour = (int) $matches[1];
                $minute = isset($matches[2]) ? (int) $matches[2] : 0;

                if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                    return ['hour' => $hour, 'minute' => $minute];
                }

                return null;
            }
        }

        return null;
    }

    public static function format(int $hour, int $minute): string
    {
        return sprintf('%02d:%02d', $hour, $minute);
    }
}
