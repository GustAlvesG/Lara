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
        try {
            Schema::create('tournaments', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->dateTime('start_date');
                $table->dateTime('end_date');
                $table->dateTime('start_date_subscription');
                $table->dateTime('end_date_subscription');
                $table->integer('max_teams')->nullable();

                $table->integer('status_id')->nullable();
                $table->foreign('status_id')->references('id')->on('status');
                
                $table->unsignedBigInteger('group_id');
                $table->foreign('group_id')->references('id')->on('place_groups');
                $table->timestamps();
            });

            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('member_by_team')->default(1);
                $table->timestamps();
            });

            Schema::create('tournaments_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->foreign('tournament_id')->references('id')->on('tournaments');
                $table->unsignedBigInteger('category_id');
                $table->foreign('category_id')->references('id')->on('categories');
                $table->decimal('entry_price', 8, 2)->default(0);
                $table->timestamps();
            });

            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('member_id');
                $table->foreign('member_id')->references('id')->on('members');
                $table->timestamps();
            });

            Schema::create('team_member', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->foreign('member_id')->references('id')->on('members');
                $table->unsignedBigInteger('team_id');
                $table->foreign('team_id')->references('id')->on('teams');
                $table->timestamps();
            });

            Schema::create('tournament_subscription', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->foreign('team_id')->references('id')->on('teams');
                $table->unsignedBigInteger('tournament_category_id');
                $table->foreign('tournament_category_id')->references('id')->on('tournaments_categories');

                $table->integer('status_id')->nullable();
                $table->foreign('status_id')->references('id')->on('status');
                $table->timestamps();
            });

            Schema::create('tournament_subscription_payment', function (Blueprint $table) {
                $table->id();
                
                $table->unsignedBigInteger('tournament_subscription_id');
                // Chave estrangeira com nome customizado para evitar o erro de limite de 64 caracteres do MySQL
                $table->foreign('tournament_subscription_id', 'fk_payment_subscription')->references('id')->on('tournament_subscription');
                
                $table->string('payment_method');
                $table->decimal('paid_amount', 10, 2);
                $table->string('payment_integration_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                
                $table->integer('status_id')->nullable();
                $table->foreign('status_id')->references('id')->on('status')->onDelete('set null');
                $table->timestamps();
            });

        } catch (\Exception $e) {
            // Se algo quebrar, aciona o down() para limpar as tabelas e devolve o erro original
            $this->down();
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_subscription_payment');
        Schema::dropIfExists('tournament_subscription');
        Schema::dropIfExists('team_member');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('tournaments_categories');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('tournaments');
    }
};