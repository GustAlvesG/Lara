<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uber_access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('contact_uuid')->index();
            $table->string('contact_phone')->index();
            $table->string('contact_name_whatsapp')->nullable();
            $table->string('poli_attendance_uuid')->nullable();
            $table->string('status')->default('aguardando_nome');
            $table->string('requester_name')->nullable();
            $table->string('club_location')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('screenshot_url', 2048)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accessed_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('uber_access_request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uber_access_request_id')->nullable()
                ->constrained('uber_access_requests')->nullOnDelete();
            $table->string('poli_message_id')->unique();
            $table->json('raw_payload');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uber_access_request_messages');
        Schema::dropIfExists('uber_access_requests');
    }
};
