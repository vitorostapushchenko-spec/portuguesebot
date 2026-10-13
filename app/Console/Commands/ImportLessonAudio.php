<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

/**
 * Attaches ready-made recordings (one OGG/Opus file per lesson, named {lesson number}.ogg, kept in
 * resources/lesson-audio) to the lessons: each file is sent once, as a voice message, to the teacher's chat to
 * obtain its Telegram file id, which is saved like a manual /audio recording (lessons.audio_file_id).
 * Run it where the bot runs, since file ids belong to the bot that uploaded them.
 */
class ImportLessonAudio extends Command
{
    protected $signature = 'lessons:import-audio
        {--dir= : Folder with the {number}.ogg files (default: resources/lesson-audio)}
        {--only= : Only these lesson numbers, e.g. 1,2,10}
        {--force : Also replace lessons that already have a recording}';

    protected $description = 'Attach the recordings in resources/lesson-audio to the lessons and upload them to Telegram.';

    public function handle(Nutgram $bot): int
    {
        $teacher = config('course.teacher_chat_id');

        if (! $teacher) {
            $this->error('TEACHER_CHAT_ID is not set; it is where the audio is uploaded to get a Telegram file id.');

            return self::FAILURE;
        }

        $dir = $this->option('dir') ?: resource_path('lesson-audio');
        $only = collect(explode(',', (string) $this->option('only')))->map(fn ($n) => (int) trim($n))->filter()->all();

        $lessons = Lesson::query()
            ->when($only !== [], fn ($q) => $q->whereIn('order', $only))
            ->when(! $this->option('force'), fn ($q) => $q->where(
                fn ($q) => $q->whereNull('audio_file_id')->orWhere('audio_file_id', '')
            ))
            ->orderBy('order')
            ->get();

        if ($lessons->isEmpty()) {
            $this->info('Nothing to import: every selected lesson already has a recording (use --force to replace).');

            return self::SUCCESS;
        }

        $done = 0;
        $failed = 0;

        foreach ($lessons as $lesson) {
            $path = $dir.DIRECTORY_SEPARATOR."{$lesson->order}.ogg";

            if (! File::exists($path)) {
                $failed++;
                $this->warn("✗ {$lesson->order}. {$lesson->phrase_pt} — no file {$lesson->order}.ogg in {$dir}");

                continue;
            }

            try {
                $message = $bot->sendVoice(
                    InputFile::make(fopen($path, 'rb'), basename($path)),
                    chat_id: $teacher,
                    caption: "{$lesson->order}. {$lesson->phrase_pt}",
                );
                $fileId = $message?->voice?->file_id;

                if (! $fileId) {
                    throw new \RuntimeException('Telegram returned no file id.');
                }

                $lesson->update(['audio_file_id' => $fileId, 'audio_kind' => 'voice']);
                $done++;
                $this->line("✓ {$lesson->order}. {$lesson->phrase_pt}");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("✗ {$lesson->order}. {$lesson->phrase_pt} — {$e->getMessage()}");
            }
        }

        $this->info("{$done} imported".($failed ? ", {$failed} failed (run again to retry only those)" : '').'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
