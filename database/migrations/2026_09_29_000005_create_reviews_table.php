<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->string('verdict')->nullable(); // good | not_yet (set as soon as the teacher taps one)
            $table->string('teacher_file_id')->nullable();
            // The bot's "record your tip / or Skip" message to the teacher, used to match
            // the teacher's next voice message back to this draft review.
            $table->unsignedBigInteger('prompt_message_id')->nullable();
            // Null until the teacher taps Send; that's when it's actually delivered.
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
