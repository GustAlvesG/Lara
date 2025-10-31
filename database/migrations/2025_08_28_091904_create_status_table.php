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
        Schema::create('status', function (Blueprint $table) {
            //Remove id increments to use a string as primary key
            $table->integer('id')->primary();
            $table->string('name')->unique();
            $table->string('description')->nullable();
        });

        // Insert default statuses
        DB::table('status')->insert([
            ['id' => 0, 'name' => 'cancelled', 'description' => 'Cancelled status'],
            ['id' => 1, 'name' => 'confirmed/active', 'description' => 'Confirmed/Active status'],
            ['id' => 2, 'name' => 'suspended/inactive', 'description' => 'Suspended/Inactive status'],
            ['id' => 3, 'name' => 'pending', 'description' => 'Pending status'],
            ['id' => 4, 'name' => 'expired', 'description' => 'Expired status'],
            ['id' => 5, 'name' => 'draft', 'description' => 'Draft status'],
            ['id' => 6, 'name' => 'archived', 'description' => 'Archived status'],
            ['id' => 7, 'name' => 'deleted', 'description' => 'Deleted status'],
            ['id' => 8, 'name' => 'completed', 'description' => 'Completed status'],
            ['id' => 9, 'name' => 'in-progress', 'description' => 'In-Progress status'],
            ['id' => 10, 'name' => 'old', 'description' => 'Old status'],
            ['id' => 11, 'name' => 'public', 'description' => 'Public status'],
            ['id' => 12, 'name' => 'private', 'description' => 'Private status'],
            ['id' => 13, 'name' => 'team', 'description' => 'Team status']
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('status')->where('id', '>=', 0)->delete();
        DB::table('status')->truncate();
        Schema::dropIfExists('status');
    }
};
