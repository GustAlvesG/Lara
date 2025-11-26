<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        #Rename description column in status table to "portuguese"
        Schema::table('status', function (Blueprint $table) {
            $table->renameColumn('description', 'portuguese');
            $table->renameColumn('name', 'status');
        });

        # Add status_id to places
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->integer('status_id')->nullable()->after('place_group_id');
            $table->foreign('status_id')->references('id')->on('status');
        });

        # Add status_id to schedule_rules
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->integer('status_id')->nullable()->after('name');
            $table->foreign('status_id')->references('id')->on('status');
        });

        # Insert default status for existing places
        DB::table('places')->update(['status_id' => 1]);

        # Insert default status for existing schedule_rules
        DB::table('schedule_rules')->update(['status_id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        #Rename portuguese column in status table back to "description"
        Schema::table('status', function (Blueprint $table) {
            $table->renameColumn('portuguese', 'description');
            $table->renameColumn('status', 'name');
        });

        # Remove status_id from places
        Schema::table('places', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
            $table->string('status')->after('place_group_id');
        });

        # Remove status_id from schedule_rules
        Schema::table('schedule_rules', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
            $table->integer('status')->after('name');
        });

        # Set default status for existing places
        DB::table('places')->update(['status' => '1']);
        # Set default status for existing schedule_rules
        DB::table('schedule_rules')->update(['status' => '1']);
    }
};
