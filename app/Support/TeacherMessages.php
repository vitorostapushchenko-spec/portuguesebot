<?php

namespace App\Support;

/**
 * Every string the bot sends to the teacher, in English, matching the tone
 * and content of the examples in spec §3.4 (Review by the Teacher).
 */
class TeacherMessages
{
    public static function newAnswerToReview(string $learnerName, ?string $username, int $lessonNumber, int $totalLessons, string $phrasePt, int $attemptNumber, int $level): string
    {
        $usernamePart = $username ? " (@{$username})" : '';

        return "🎧 New answer to review\n".
            "Learner: {$learnerName}{$usernamePart}\n".
            "Lesson {$lessonNumber} of {$totalLessons}: {$phrasePt}\n".
            "Attempt {$attemptNumber} · Level: {$level}%";
    }

    public static function askForTip(): string
    {
        return 'Record the phrase the right way, with your tip, as a voice message. Or tap Skip.';
    }

    public static function confirmSend(string $learnerName, string $verdictLabel, bool $hasRecording): string
    {
        $recordingPart = $hasRecording ? ' + your voice message' : '';

        return "Send to {$learnerName}?\n{$verdictLabel}{$recordingPart}";
    }

    public static function sentConfirmation(string $learnerName): string
    {
        return "Sent to {$learnerName} ✓";
    }

    /** Good is sent at once, so a second tap on the same answer must not send the praise again. */
    public static function alreadySentGood(string $learnerName): string
    {
        return "Already sent to {$learnerName} ✓ (✅ Good)";
    }

    public static function dailySummary(int $pendingCount, array $newlyPausedNames): string
    {
        $lines = ["📋 {$pendingCount} answer".($pendingCount === 1 ? '' : 's').' waiting for review'];

        if (! empty($newlyPausedNames)) {
            $lines[] = 'Paused for inactivity: '.implode(', ', $newlyPausedNames);
        }

        return implode("\n", $lines);
    }

    public static function learnerBlocked(string $learnerName): string
    {
        return "Could not deliver the feedback — {$learnerName} has blocked the bot.";
    }

    public static function notTeacher(): string
    {
        return 'This command is only available to the course teacher.';
    }

    public static function noPendingAnswers(): string
    {
        return 'Nothing waiting for review right now.';
    }

    // --- The teacher's recordings of the phrases (/audio) ---

    /** @param list<int> $missing */
    public static function audioStatus(int $recorded, int $total, array $missing): string
    {
        $shown = array_slice($missing, 0, 25);
        $list = implode(', ', $shown);

        if (count($missing) > count($shown)) {
            $list .= ' and '.(count($missing) - count($shown)).' more';
        }

        return "🎙 Recordings: {$recorded} of {$total} lessons\n".
            "Missing: {$list}\n\n".
            'Tap the button, or send /audio and the number of a lesson (for example /audio 12).';
    }

    public static function audioAllRecorded(int $total): string
    {
        return "🎙 All {$total} lessons have a recording ✓\nTo record one again, send /audio and its number (for example /audio 12).";
    }

    public static function audioAskRecording(int $lessonNumber, string $phrasePt, bool $hasRecording): string
    {
        $text = "🎙 Lesson {$lessonNumber}: {$phrasePt}\n";

        return $hasRecording
            ? $text.'This is the recording it has now. Send a new voice message to replace it, or tap Cancel to keep it.'
            : $text.'Send a voice message saying this phrase. Learners will get it together with the lesson.';
    }

    public static function audioSaved(int $lessonNumber, string $phrasePt, int $missingCount): string
    {
        $rest = match (true) {
            $missingCount === 0 => 'Every lesson has a recording now.',
            $missingCount === 1 => '1 lesson still has no recording.',
            default => "{$missingCount} lessons still have no recording.",
        };

        return "Saved for lesson {$lessonNumber} ✓ ({$phrasePt})\n{$rest}";
    }

    public static function audioCancelled(): string
    {
        return 'Cancelled. Nothing was changed.';
    }

    public static function audioNoSuchLesson(int $lessonNumber, int $total): string
    {
        return "There is no lesson {$lessonNumber}. The lessons go from 1 to {$total}.";
    }

    public static function audioUsage(): string
    {
        return 'Send /audio to see which lessons have a recording, or /audio and a number (for example /audio 12) to record that lesson.';
    }
}
