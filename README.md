# Portuguese Bot

Telegram bot that teaches everyday Brazilian Portuguese to Russian speakers,
one phrase a day, with a human teacher reviewing voice answers. Full behavior
spec: see the technical specification PDF this project was built from
(sections referenced as `§x.y` throughout the code).

Independent Laravel project — own Telegram bot token, own database, own
containers — meant to run on the **same VPS as the `lunchbot` project**
(colocated to avoid paying for a second server), not sharing any code or
data with it.

## Stack

- Laravel 13 (PHP 8.3 — run it via Docker; local PHP on this machine may be
  older, see below)
- [nutgram/laravel](https://github.com/nutgram/laravel) for the Telegram bot
- Postgres (own instance/container, not shared with lunchbot)
- Long polling (`php artisan nutgram:run`), no webhook, no exposed ports —
  nothing here needs a public domain or TLS.

## First-time setup

1. Create the bot with [@BotFather](https://t.me/BotFather) and copy the
   token.
2. Get the teacher's Telegram numeric chat id (e.g. via @userinfobot).
3. Copy `.env.example` to `.env` and fill in `TELEGRAM_TOKEN` and
   `TEACHER_CHAT_ID`.
4. Generate an app key: `php artisan key:generate` (run inside the `app`
   container — see below — since local PHP here doesn't satisfy Laravel 13's
   PHP ^8.3 requirement).
5. `docker compose up -d --build`. On first boot the `app` container runs
   migrations and seeds the 50 lessons automatically.

## Illustrations

The spec calls for one PNG illustration per lesson (§5). The bot works
without them (falls back to a text-only lesson message), but to add them:

1. Open `storage/app/lesson-image-prompts.md` — one ready-to-use prompt per
   lesson, following the spec's style guide.
2. Generate each image with your image generator of choice.
3. Save it as `storage/app/public/lessons/{lesson_number}.png` (e.g. `1.png`
   for lesson 1). No code change or restart needed — the path is already
   wired up per lesson.

## Recordings of the phrases

Each lesson can come with the teacher's recording of the phrase, so the learner hears how it sounds before
answering. The lesson arrives first (illustration, phrase, translations, pronunciation) and the recording right
after it; `/today` sends both again. A lesson without a recording is sent as before.

The teacher records in the chat with the bot (only the teacher's account can):

- `/audio` shows how many lessons have a recording and which are missing, with a button to record the next one.
- `/audio 12` records lesson 12: the bot shows the phrase and the teacher's next voice message is saved for it.
  If the lesson already has a recording, the bot plays it first and the new one replaces it (Cancel keeps it).
- After each recording the bot offers the next lesson that has none, so the whole course is one tap and one
  voice message per phrase. A sound file (mp3, ogg) sent instead of a voice message is accepted too.

The recordings are Telegram file ids kept in the database (`lessons.audio_file_id`), not files in the project:
a deploy and the re-seeding of `lessons.json` keep them, and the nightly database backup includes them. They
belong to the bot they were recorded with, so a second bot used for local work has none.

The code is in `app/Telegram/Handlers/LessonAudioHandler.php` (the teacher's side) and
`app/Services/LessonDelivery.php` (sending a lesson); `tests/Feature/LessonAudioTest.php` covers both.

### Generated recordings (Azure voice)

Instead of recording by hand, the whole course can be voiced with Azure AI Speech (`pt-BR-AntonioNeural`):

1. Create a *Speech* resource in Azure (the free F0 tier gives 500 000 characters a month; the 50 phrases are
   a few thousand), copy its key and region.
2. Put `AZURE_SPEECH_KEY` and `AZURE_SPEECH_REGION` (and optionally `TTS_VOICE`, `TTS_RATE`) in the `.env` of the
   environment that runs the command — on the server, since Telegram file ids belong to the bot that uploaded them.
3. `php artisan lessons:generate-audio` (inside the `app` container). Only lessons without a recording are done;
   `--only=1,2` limits it, `--force` replaces existing ones, `--no-upload` just writes the files to
   `storage/app/lesson-audio/`. Each phrase is sent once to the teacher's chat to obtain its file id, then saved
   exactly like a manual recording, so nothing else changes. Failed lessons are listed; run it again to retry them.

### Ready-made recordings (files)

Recordings made elsewhere go in `resources/lesson-audio/{lesson number}.ogg` (OGG/Opus; they are baked into the
image by the deploy). After the deploy, on the server: `php artisan lessons:import-audio` sends each file once to the
teacher's chat and saves its file id, exactly like a manual recording. Lessons that already have one are skipped
(`--force` replaces them, `--only=22,29` limits it).

## Course content

`database/data/lessons.json` holds all 50 lessons (Portuguese, Russian,
English, Cyrillic pronunciation, category) as data, not code — edit and
re-run `php artisan db:seed --class=LessonSeeder` to apply changes without
touching the bot's logic.

## Teacher review

Every voice answer is forwarded to the teacher with two buttons:

- **✅ Good** sends the learner the feedback at once, as a reply to their voice
  message: the lesson number, the phrase and «Молодец! ✅ Всё правильно!». No
  recording is asked for and there is no confirmation step. Tapping Good again on
  an answer that already got it only tells the teacher "Already sent".
- **❌ Not yet** asks the teacher to record the phrase the right way as a voice
  message (or tap Skip), then to confirm with Send or Start over. The learner then
  gets «Пока не совсем…» and the recording.

The wording is in `app/Support/BotMessages.php` (`verdictGood()`), the flow in
`app/Services/ReviewFlow.php`, and `tests/Feature/TeacherReviewTest.php` covers it.

## Tests

```
docker compose run --rm app php artisan test
```

(Local PHP on this machine is older than Laravel 13 requires, which is why
tests need to run through the `app` container's PHP 8.3.)

## Running on the server

The bot runs on the `lunch-bot` VM (GCP, `us-central1-a`, e2-micro), next to
lunchbot, in `~/portuguesebot`. It does not depend on this PC being on. The
project is self-contained there (its own `postgres`, `app` and `scheduler`
containers, no Redis, no ports exposed), and every container has a memory cap
because the VM has 1 GB shared with lunchbot.

- **Image:** `docker/Dockerfile.prod` bakes the code and dependencies in
  (`APP_ENV=production`, `APP_DEBUG=false`). The server never builds; the image
  is built here and uploaded.
- **Settings:** `~/portuguesebot/.env` on the server, created once from
  `.env.example` (token, teacher chat id, `APP_KEY`, a real database password).
  It is never overwritten by a deploy. Logs go to `docker logs`.
- **Compose file:** `docker-compose.prod.yml` is uploaded as `docker-compose.yml`
  there, so on the server it is just `sudo docker compose ...`.

**Update the bot** (code or lessons, from Git Bash in the project root):

```
bash deploy/deploy.sh
```

It builds, uploads (about 230 MB, around ten minutes on a slow connection),
loads the image and restarts the containers. The database is not touched; the
`app` container runs pending migrations and re-seeds `lessons.json` on start.

**Look at it:**

```
gcloud compute ssh lunch-bot --zone us-central1-a --command "sudo docker logs --tail 30 ptbot_app"
gcloud compute ssh lunch-bot --zone us-central1-a --command "sudo docker compose -f portuguesebot/docker-compose.yml ps"
```

**Backups:** cron runs `deploy/backup.sh` every night at 03:15 (server time),
keeping 14 daily dumps in `~/portuguesebot/backups`. They live on the same
disk as the database, so they protect against mistakes, not against losing the
VM; copy them elsewhere if that matters. To restore, see the comment at the top
of `deploy/backup.sh`.

**Do not run the local `docker compose up` with the real token while the server
bot is running.** Telegram lets only one long-polling process read a bot's
messages, so the two would fight and each would miss some. For local work,
create a second bot with @BotFather and put its token in the local `.env`.
