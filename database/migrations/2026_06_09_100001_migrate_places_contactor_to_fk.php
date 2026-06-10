<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->unsignedBigInteger('contactor_id')->nullable()->after('contactor');
            $table->foreign('contactor_id')->references('id')->on('contactors')->nullOnDelete();
        });

        // Migrar dados existentes: criar registros em contactors para cada entity_id distinto
        $distinctContactors = DB::table('places')
            ->whereNotNull('contactor')
            ->where('contactor', '!=', '')
            ->select('contactor')
            ->distinct()
            ->pluck('contactor');

        foreach ($distinctContactors as $entityId) {
            $contactorId = DB::table('contactors')->insertGetId([
                'name'       => $entityId,
                'entity_id'  => $entityId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('places')
                ->where('contactor', $entityId)
                ->update(['contactor_id' => $contactorId]);
        }

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('contactor');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('contactor')->nullable()->after('name');
        });

        $places = DB::table('places')->whereNotNull('contactor_id')->get();
        foreach ($places as $place) {
            $contactor = DB::table('contactors')->find($place->contactor_id);
            if ($contactor) {
                DB::table('places')->where('id', $place->id)->update(['contactor' => $contactor->entity_id]);
            }
        }

        Schema::table('places', function (Blueprint $table) {
            $table->dropForeign(['contactor_id']);
            $table->dropColumn('contactor_id');
        });
    }
};
