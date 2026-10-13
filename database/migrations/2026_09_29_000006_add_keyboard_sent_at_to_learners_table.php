<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            // Set once the persistent "Next lesson / My progress" reply keyboard
            // has been sent, so it's only pushed to the learner one time.
            $table->timestamp('keyboard_sent_at')->nullable()->after('inactivity_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('keyboard_sent_at');
        });
    }
};
