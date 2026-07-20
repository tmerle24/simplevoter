<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_option_id')->constrained()->cascadeOnDelete();
            $table->string('voter_token', 64);
            $table->timestamps();

            $table->unique(['poll_option_id', 'voter_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
