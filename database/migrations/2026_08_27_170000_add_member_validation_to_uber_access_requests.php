<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uber_access_requests', function (Blueprint $table) {
            $table->string('member_validation')->nullable()->after('screenshot_url');
            $table->string('member_validation_name')->nullable()->after('member_validation');
            $table->timestamp('member_validated_at')->nullable()->after('member_validation_name');
        });
    }

    public function down(): void
    {
        Schema::table('uber_access_requests', function (Blueprint $table) {
            $table->dropColumn(['member_validation', 'member_validation_name', 'member_validated_at']);
        });
    }
};
