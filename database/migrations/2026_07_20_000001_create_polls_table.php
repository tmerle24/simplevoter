<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->string('public_token', 12)->unique();
            $table->string('manage_token', 64)->unique();

            $table->string('question');
            $table->text('description')->nullable();

            $table->enum('result_visibility', ['live', 'after_vote', 'hidden_until_closed'])
                  ->default('live');
            $table->enum('question_name_mode', ['hidden', 'optional', 'required'])
                  ->default('optional');
            $table->boolean('allows_multiple_choice')->default(false);

            $table->boolean('is_active')->default(true);
            $table->string('creator_ip')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            // Vorbereitung für spätere Event-Integration (Abschnitt 9 der Spec):
            // $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
