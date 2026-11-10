<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila de espera das modalidades (InfoClube › Fila de Espera, 10/11/2026).
 *
 * Só NÃO sócios: a fila dos sócios fica no MultiClubes, e esta não tem
 * vínculo nenhum com ele. Turmas cadastradas aqui, pessoas identificadas pelo
 * CPF (uma pessoa pode estar em várias filas), a inscrição de cada pessoa em
 * cada turma (a ordem é `joined_at`) e as ligações da secretaria.
 *
 * Idempotente e com nomes de índice curtos: o nome automático passa de 64
 * caracteres no MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('waitlist_classes')) {
            Schema::create('waitlist_classes', function (Blueprint $table) {
                $table->id();
                $table->string('modality', 100);
                $table->string('name', 150);
                $table->string('notes', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['modality', 'name'], 'wl_classes_modality_name_uq');
            });
        }

        if (!Schema::hasTable('waitlist_people')) {
            Schema::create('waitlist_people', function (Blueprint $table) {
                $table->id();
                $table->string('cpf', 11)->unique('wl_people_cpf_uq');
                $table->string('name', 255);
                $table->date('birth_date')->nullable();
                $table->string('phone', 20);
                $table->string('phone_alt', 20)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('guardian_name', 255)->nullable();
                $table->string('guardian_phone', 20)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('name', 'wl_people_name_idx');
            });
        }

        if (!Schema::hasTable('waitlist_entries')) {
            Schema::create('waitlist_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('waitlist_class_id')->constrained('waitlist_classes', 'id', 'wl_entries_class_fk');
                $table->foreignId('waitlist_person_id')->constrained('waitlist_people', 'id', 'wl_entries_person_fk');
                $table->dateTime('joined_at');
                $table->string('status', 20)->default('waiting');
                $table->dateTime('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->string('close_reason', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['waitlist_class_id', 'status', 'joined_at'], 'wl_entries_queue_idx');
                $table->index(['waitlist_person_id', 'status'], 'wl_entries_person_idx');
            });
        }

        if (!Schema::hasTable('waitlist_contacts')) {
            Schema::create('waitlist_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('waitlist_entry_id')->constrained('waitlist_entries', 'id', 'wl_contacts_entry_fk');
                $table->string('result', 20);
                $table->string('notes', 255)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['waitlist_entry_id', 'created_at'], 'wl_contacts_entry_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_contacts');
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('waitlist_people');
        Schema::dropIfExists('waitlist_classes');
    }
};
