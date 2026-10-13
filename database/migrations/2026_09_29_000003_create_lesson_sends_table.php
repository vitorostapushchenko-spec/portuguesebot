<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('delivery_type'); // scheduled | repeated | next
            $table->string('status')->default('open'); // open | answered | missed
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['learner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_sends');
    }
};
