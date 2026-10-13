<?php

namespace App\Support;

/**
 * Every string the bot sends to a learner, in Russian, verbatim from the spec's
 * Appendix A. Centralized here so wording can be audited against the spec in
 * one place instead of scattered across commands/handlers.
 */
class BotMessages
{
    public static function welcomeAndTimeQuestion(): string
    {
        return 'Привет! Каждый день я буду присылать тебе одну фразу на португальском для практики. Во сколько её присылать?';
    }

    public static function timeConfirmed(string $time): string
    {
        return "Готово! Урок будет приходить каждый день в {$time}. Хочешь больше одного в день? Отправь /next после того, как ответишь на урок.";
    }

    public static function timeNotUnderstood(): string
    {
        return 'Извини, не получилось разобрать. Выбери время на кнопках или напиши его так: 08:30.';
    }

    public static function startPressedAgain(string $time, int $lessonNumber, int $totalLessons, int $level): string
    {
        return "Уроки приходят каждый день в {$time}. Ты на уроке {$lessonNumber} из {$totalLessons}. Уровень: {$level}%.";
    }

    public static function greetingWord(\DateTimeInterface $now): string
    {
        $hour = (int) $now->format('H');

        return match (true) {
            $hour < 12 => 'Доброе утро',
            $hour < 18 => 'Добрый день',
            default => 'Добрый вечер',
        };
    }

    public static function dailyLessonGreeting(\DateTimeInterface $now, bool $isRepeat): string
    {
        $tail = $isRepeat ? 'Давай попробуем эту фразу ещё раз.' : 'Давай сделаем урок на сегодня.';

        return self::greetingWord($now).'! '.$tail;
    }

    public static function taskLine(bool $hasVariant): string
    {
        return $hasVariant
            ? 'Отправь голосовое сообщение и произнеси один из вариантов на португальском.'
            : 'Отправь голосовое сообщение и произнеси эту фразу на португальском.';
    }

    /** Under the teacher's recording of the phrase, which comes right after the lesson. */
    public static function lessonAudioCaption(): string
    {
        return '🎧 Послушай, как звучит эта фраза.';
    }

    public static function answerReceived(int $level, string $levelMessage, string $nextTime): string
    {
        return "Получено! Преподаватель послушает и пришлёт тебе отзыв. Твой уровень: {$level}% · {$levelMessage} Следующий урок завтра в {$nextTime}, или отправь /next прямо сейчас.";
    }

    public static function anotherAttemptReceived(): string
    {
        return 'Отправлено преподавателю!';
    }

    public static function notAVoiceMessage(): string
    {
        return 'Пожалуйста, ответь голосовым сообщением: удерживай кнопку микрофона и произнеси фразу на португальском.';
    }

    public static function noLessonToAnswer(?string $nextTime): string
    {
        if ($nextTime === null) {
            return 'Сейчас нет урока, на который нужно ответить.';
        }

        return "Сейчас нет урока, на который нужно ответить. Следующий урок придёт завтра в {$nextTime}.";
    }

    public static function feedbackHeader(int $lessonNumber): string
    {
        return "Отзыв на урок {$lessonNumber}";
    }

    /** What the learner reads when the teacher taps Good: «Молодец!» and that it is all right, three words in all. */
    public static function verdictGood(): string
    {
        return 'Молодец! ✅ Всё правильно!';
    }

    public static function verdictNotYet(): string
    {
        return '❌Пока не совсем. Послушай, как правильно, и попробуй ещё раз.';
    }

    public static function levelLine(int $level): string
    {
        return "Твой уровень: {$level}%";
    }

    /**
     * Level = (distinct lessons answered) × 2 — this is course progress, not
     * accuracy. Bands are gradual yellow → green, no red. Exactly 100% gets
     * its own "course complete" phrase instead of the band-4 phrase.
     */
    public static function levelMessage(int $level): string
    {
        if ($level >= 100) {
            return 'Курс пройден! 🎉';
        }

        return match (true) {
            $level >= 76 => 'Ты почти у цели!',
            $level >= 51 => 'Больше половины пути!',
            $level >= 26 => 'Продолжай в том же духе!',
            default => 'Отличное начало!',
        };
    }

    public static function progress(int $level, int $answered, int $totalLessons, ?string $nextTime): string
    {
        $nextPart = $nextTime ? "завтра в {$nextTime}" : '—';

        return "📊Твой прогресс · Отвечено уроков: {$answered} из {$totalLessons} · {$level}% · Следующий урок: {$nextPart}";
    }

    public static function nextOpening(): string
    {
        return 'Вот твой следующий урок!';
    }

    public static function nextRefused(): string
    {
        return 'Сначала отправь голосовое сообщение для текущего урока, а потом используй /next.';
    }

    public static function nextNoLessonsLeft(): string
    {
        return 'Все 50 уроков уже у тебя!';
    }

    public static function courseFinished(): string
    {
        return '🌸Ура, все 50 уроков пройдены! Огромное-огромное спасибо за каждый день практики вместе со мной. Мне было очень-очень приятно 😊💛 Твой португальский расцветает! Muito obrigado! 🇧🇷✨';
    }

    public static function pausedForInactivity(): string
    {
        return 'Скучаю по тебе! 🌷 От тебя не было ответа 7 дней, поэтому уроки на паузе. Отправь /start, когда захочешь продолжить. Я жду!';
    }

    public static function pauseCommand(): string
    {
        return 'Уроки на паузе. Отправь /start, чтобы продолжить.';
    }

    public static function help(): string
    {
        return 'Каждый день я присылаю одну фразу на португальском. Ответь голосовым сообщением, и преподаватель пришлёт тебе отзыв. /next: следующий урок сейчас · /progress: твой уровень · /time: изменить время · /today: текущий урок · /pause: пауза';
    }
}
