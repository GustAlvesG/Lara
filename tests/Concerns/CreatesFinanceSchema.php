<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O FinanceLedger junta Reservas, Torneios e Ingressos numa consulta só, então
 * qualquer teste que passe por ele (Caixa, Recebimentos, Visão geral) precisa
 * das tabelas das três origens. Os testes de cada módulo criam só as suas;
 * este completa as que faltarem, com as colunas que a consulta lê.
 *
 * Use depois do schema do módulo (createTicketSchema, createTournamentSchema,
 * createSocialBookingSchema): o que já existe fica como está.
 */
trait CreatesFinanceSchema
{
    protected function createFinanceSchema(): void
    {
        $create = function (string $table, \Closure $columns) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $columns);
            }
        };

        $create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('cpf', 20)->nullable();
        });

        $create('place_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        $create('places', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_group_id')->nullable();
            $table->string('name')->nullable();
        });

        $create('schedule_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method');
            $table->decimal('paid_amount', 10, 2);
            $table->decimal('refunded_amount', 10, 2)->nullable();
            $table->string('payment_integration_id')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->string('manual_notes', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->integer('status_id')->nullable();
            $table->unsignedBigInteger('refunded_by')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        $create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('schedule_payment_id')->nullable();
            $table->integer('status_id')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->dateTime('start_schedule')->nullable();
            $table->dateTime('end_schedule')->nullable();
            $table->timestamps();
        });

        $create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $create('tournament_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_id');
            $table->string('name')->nullable();
        });

        $create('tournament_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('payment_method', 40);
            $table->decimal('paid_amount', 10, 2);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_integration_id')->nullable();
            $table->string('status', 20)->default('paid');
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('refunded_by')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->string('manual_notes', 500)->nullable();
            $table->timestamps();
        });

        $create('tournament_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_category_id');
            $table->unsignedBigInteger('tournament_payment_id')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('name')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status', 20)->default('paid');
        });

        $create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_event_id');
            $table->string('buyer_name')->nullable();
            $table->string('buyer_cpf', 11)->nullable();
        });

        $create('ticket_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_order_id');
            $table->string('payment_method', 40);
            $table->decimal('paid_amount', 10, 2);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_integration_id')->nullable();
            $table->string('status', 20)->default('paid');
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('refunded_by')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->string('manual_notes', 500)->nullable();
            $table->timestamps();
        });

        $create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_order_id');
        });

        // Visão geral: o pago e o a pagar dos freelancers (scope awaitingFinance).
        $create('freelancer_services', function (Blueprint $table) {
            $table->id();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('paid')->default(false);
            $table->dateTime('paid_at')->nullable();
            $table->integer('status_id')->default(1);
            $table->dateTime('freelancer_signed_at')->nullable();
            $table->dateTime('coordinator_signed_at')->nullable();
            $table->dateTime('manager_approved_at')->nullable();
            $table->dateTime('director_approved_at')->nullable();
            $table->dateTime('amended_at')->nullable();
            $table->softDeletes();
        });
    }
}
