<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

/**
 * Generates the pronunciation recording of each lesson's Portuguese phrase with Azure AI Speech
 * (default voice pt-BR-AntonioNeural) and saves it exactly where the teacher's own recordings go:
 * lessons.audio_file_id + audio_kind, so LessonDelivery sends it with the lesson with no other change.
 *
 * Telegram only gives a file id after an upload, so each generated file is sent once, as a voice message,
 * to the teacher's chat (the same bot that serves the learners must do this, since file ids belong to a bot).
 * The OGG/Opus file is also kept in storage/app/lesson-audio/{order}.ogg and reused if the upload fails.
 *
 * Key and region come from the environment (AZURE_SPEECH_KEY, AZURE_SPEECH_REGION), never from the code.
 */
class GenerateLessonAudio extends Command
{
    protected $signature = 'lessons:generate-audio
        {--only= : Only these lesson numbers, e.g. 1,2,10}
        {--force : Also replace lessons that already have a recording (the teacher\'s too)}
        {--no-upload : Only create the files in storage/app/lesson-audio, do not send them to Telegram}';

    protected $description = 'Generate the pronunciation audio of the lessons with Azure AI Speech and attach it to them.';

    public function handle(Nutgram $bot): int
    {
        $key = config('services.azure_speech.key');
        $region = config('services.azure_speech.region');
        $voice = config('services.azure_speech.voice');
        $rate = config('services.azure_speech.rate');
        $teacher = config('course.teacher_chat_id');

        if (! $key || ! $region) {
            $this->error('Set AZURE_SPEECH_KEY and AZURE_SPEECH_REGION in the .env of this environment first.');

            return self::FAILURE;
        }

        $upload = ! $this->option('no-upload');

        if ($upload && ! $teacher) {
            $this->error('TEACHER_CHAT_ID is not set; it is where the audio is uploaded to get a Telegram file id.');

            return self::FAILURE;
        }

        $only = collect(explode(',', (string) $this->option('only')))
            ->map(fn ($n) => (int) trim($n))->filter()->all();

        $lessons = Lesson::query()
            ->when($only !== [], fn ($q) => $q->whereIn('order', $only))
            ->when(! $this->option('force'), fn ($q) => $q->where(
                fn ($q) => $q->whereNull('audio_file_id')->orWhere('audio_file_id', '')
            ))
            ->orderBy('order')
            ->get();

        if ($lessons->isEmpty()) {
            $this->info('Nothing to generate: every selected lesson already has a recording (use --force to replace).');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(Storage::disk('local')->path('lesson-audio'));
        $failed = 0;

        foreach ($lessons as $lesson) {
            $path = Storage::disk('local')->path("lesson-audio/{$lesson->order}.ogg");

            try {
                if (! File::exists($path) || $this->option('force')) {
                    File::put($path, $this->synthesize($lesson->phrase_pt, $key, $region, $voice, $rate));
                }

                if ($upload) {
                    $message = $bot->sendVoice(
                        InputFile::make($path),
                        chat_id: $teacher,
                        caption: "{$lesson->order}. {$lesson->phrase_pt}",
                    );
                    $fileId = $message?->voice?->file_id;

                    if (! $fileId) {
                        throw new \RuntimeException('Telegram returned no file id.');
                    }

                    $lesson->update(['audio_file_id' => $fileId, 'audio_kind' => 'voice']);
                }

                $this->line("✓ {$lesson->order}. {$lesson->phrase_pt}");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("✗ {$lesson->order}. {$lesson->phrase_pt} — {$e->getMessage()}");
            }
        }

        $this->info(($lessons->count() - $failed).' of '.$lessons->count().' done.'.($failed ? " {$failed} failed; run the command again to retry only those." : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function synthesize(string $text, string $key, string $region, string $voice, string $rate): string
    {
        $ssml = sprintf(
            "<speak version='1.0' xml:lang='pt-BR'><voice name='%s'><prosody rate='%s'>%s</prosody></voice></speak>",
            $voice,
            $rate,
            htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $key,
            'Content-Type' => 'application/ssml+xml',
            'X-Microsoft-OutputFormat' => 'ogg-24khz-16bit-mono-opus',
            'User-Agent' => 'portuguesebot',
        ])->withBody($ssml, 'application/ssml+xml')
            ->timeout(30)->retry(2, 1000)
            ->post("https://{$region}.tts.speech.microsoft.com/cognitiveservices/v1");

        if ($response->failed()) {
            throw new \RuntimeException("Azure answered HTTP {$response->status()}: ".substr($response->body(), 0, 200));
        }

        return $response->body();
    }
}
