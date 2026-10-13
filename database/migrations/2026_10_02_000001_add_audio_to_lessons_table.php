<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The teacher's recording of each phrase, sent to the learner together with the lesson.
     * It is a Telegram file id (the teacher records it in the chat with the bot, see /audio), so it lives
     * only in the database: LessonSeeder never touches these columns, and a deploy keeps them.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('audio_file_id')->nullable();
            // "voice" (recorded with the microphone button) or "audio" (a sound file): Telegram does not let
            // a file id of one kind be sent as the other.
            $table->string('audio_kind', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['audio_file_id', 'audio_kind']);
        });
    }
};
