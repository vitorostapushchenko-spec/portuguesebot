<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->unique();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            // Local (America/Sao_Paulo) time of day the daily lesson is sent. Null until set (3.1).
            $table->time('lesson_time')->nullable();
            $table->unsignedTinyInteger('current_lesson_number')->default(1);
            $table->string('status')->default('setting_up');
            // Consecutive unanswered sends of the CURRENT lesson; drives the 7-day pause (3.7).
            $table->unsignedTinyInteger('consecutive_unanswered')->default(0);
            $table->timestamp('course_started_at')->nullable();
            // Set once a pause-for-inactivity has been reported in a teacher summary (3.4).
            $table->timestamp('inactivity_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
    }
};
