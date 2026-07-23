<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('event_id');
        });

        // Bestehende Event-Polls nach created_at nummerieren
        $events = DB::table('polls')
            ->whereNotNull('event_id')
            ->select('event_id')
            ->distinct()
            ->pluck('event_id');

        foreach ($events as $eventId) {
            $polls = DB::table('polls')
                ->where('event_id', $eventId)
                ->orderBy('created_at')
                ->pluck('id');

            foreach ($polls as $index => $id) {
                DB::table('polls')->where('id', $id)->update(['sort_order' => $index]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('polls', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
