<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingressos (módulo novo, 27/10/2026). Regras e modelagem em
 * docs/funcionalidades/ingressos.md.
 *
 *   ticket_events → ticket_types → ticket_lots (preço sócio e não sócio)
 *   ticket_events → ticket_orders → tickets (um por pessoa, nominal)
 *                                 → ticket_payments (eRede ou baixa manual)
 *
 * Situações gravadas como texto (constantes nos models). FKs e índices com
 * nome curto e explícito (limite de 64 do MySQL; o SQLite dos testes não
 * reclama). Cada tabela só é criada se ainda não existir, para o deploy poder
 * rodar de novo depois de uma falha no meio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_events')) {
            Schema::create('ticket_events', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->longText('rules')->nullable();
                $table->string('image')->nullable();
                $table->string('venue')->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->dateTime('sales_starts_at');
                $table->dateTime('sales_ends_at');
                // Lotação do evento somando todos os tipos; nulo = sem limite.
                $table->unsignedInteger('capacity')->nullable();
                $table->unsignedTinyInteger('max_per_order')->default(10);
                $table->string('status', 20)->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'tev_creator_fk');
                $table->softDeletes();
                $table->timestamps();

                $table->index('status', 'tev_status_idx');
            });
        }

        if (! Schema::hasTable('ticket_types')) {
            Schema::create('ticket_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_event_id')->constrained('ticket_events', 'id', 'tty_event_fk')->cascadeOnDelete();
                $table->string('name');
                $table->string('description', 1000)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        // O lote é do tipo: quantidade, janela de venda e os dois preços.
        // `price_non_member` nulo = o tipo não é vendido a não sócio neste lote.
        if (! Schema::hasTable('ticket_lots')) {
            Schema::create('ticket_lots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_type_id')->constrained('ticket_types', 'id', 'tlot_type_fk')->cascadeOnDelete();
                $table->string('name');
                $table->unsignedSmallInteger('sequence')->default(1);
                $table->unsignedInteger('quantity');
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->decimal('price_member', 10, 2);
                $table->decimal('price_non_member', 10, 2)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(['ticket_type_id', 'sequence'], 'tlot_type_seq_idx');
            });
        }

        // Cupom de desconto do evento: abate valor fixo ou porcentagem de cada
        // ingresso a que se aplica. `ticket_type_ids` nulo = todos os tipos;
        // `max_uses` conta ingressos com desconto (nulo = sem limite).
        if (! Schema::hasTable('ticket_coupons')) {
            Schema::create('ticket_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_event_id')->constrained('ticket_events', 'id', 'tcp_event_fk')->cascadeOnDelete();
                $table->string('code', 40);
                $table->string('description')->nullable();
                $table->string('kind', 10);
                $table->decimal('value', 10, 2);
                $table->string('audience', 12)->default('all');
                $table->json('ticket_type_ids')->nullable();
                $table->unsignedInteger('max_uses')->nullable();
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'tcp_creator_fk');
                $table->timestamps();

                $table->unique(['ticket_event_id', 'code'], 'tcp_event_code_uq');
            });
        }

        // O pedido: de um sócio (só para ele mesmo) ou de um não sócio sem
        // conta (para uma ou mais pessoas). Os dados de quem compra são
        // copiados no momento do pedido.
        if (! Schema::hasTable('ticket_orders')) {
            Schema::create('ticket_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_event_id')->constrained('ticket_events', 'id', 'tord_event_fk');
                $table->string('buyer_kind', 10);
                $table->foreignId('member_id')->nullable()->constrained('members', 'id', 'tord_member_fk');
                $table->string('buyer_cpf', 11);
                $table->string('buyer_name');
                $table->string('buyer_email')->nullable();
                $table->string('buyer_phone', 30)->nullable();
                $table->string('buyer_title', 20)->nullable();
                $table->decimal('total', 10, 2);
                // Cupom usado (com o código copiado) e o desconto somado do pedido.
                $table->foreignId('ticket_coupon_id')->nullable()->constrained('ticket_coupons', 'id', 'tord_coupon_fk')->nullOnDelete();
                $table->string('coupon_code', 40)->nullable();
                $table->decimal('discount_total', 10, 2)->default(0);
                $table->string('status', 20)->default('pending_payment');
                // Até quando o pedido pendente segura o estoque.
                $table->dateTime('hold_until')->nullable();
                // Token do link do não sócio, cifrado (o e-mail precisa reenviá-lo).
                $table->text('access_token')->nullable();
                $table->string('origin', 20)->default('site');
                // Cortesia do balcão: ingressos a preço zero por decisão da equipe.
                $table->boolean('courtesy')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'tord_creator_fk');
                $table->dateTime('paid_at')->nullable();
                $table->dateTime('expired_at')->nullable();
                $table->dateTime('withdrawn_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users', 'id', 'tord_canceller_fk');
                $table->string('cancel_reason', 500)->nullable();
                $table->timestamps();

                $table->index(['ticket_event_id', 'status'], 'tord_event_status_idx');
                $table->index('buyer_cpf', 'tord_cpf_idx');
                $table->index(['status', 'hold_until'], 'tord_hold_idx');
            });
        }

        // Pagamento do pedido: eRede (`payment_integration_id` = tid) ou baixa
        // manual. Um por pedido. Mesmo desenho do TournamentPayment.
        if (! Schema::hasTable('ticket_payments')) {
            Schema::create('ticket_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_order_id')->constrained('ticket_orders', 'id', 'tpy_order_fk');
                $table->string('payment_method', 40);
                $table->decimal('paid_amount', 10, 2);
                $table->dateTime('paid_at')->nullable();
                $table->string('payment_integration_id')->nullable();
                $table->string('status', 20)->default('paid');
                $table->decimal('refunded_amount', 10, 2)->default(0);
                $table->foreignId('refunded_by')->nullable()->constrained('users', 'id', 'tpy_refunder_fk');
                $table->dateTime('refunded_at')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->foreignId('recorded_by')->nullable()->constrained('users', 'id', 'tpy_recorder_fk');
                $table->string('manual_notes', 500)->nullable();
                $table->timestamps();

                $table->index('payment_integration_id', 'tpy_integration_idx');
            });
        }

        // O ingresso: nominal (nome e CPF de quem entra), com o preço e o lote
        // congelados no pedido. `code` vai no QR e na portaria.
        if (! Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_order_id')->constrained('ticket_orders', 'id', 'tkt_order_fk')->cascadeOnDelete();
                $table->foreignId('ticket_event_id')->constrained('ticket_events', 'id', 'tkt_event_fk');
                $table->foreignId('ticket_type_id')->constrained('ticket_types', 'id', 'tkt_type_fk');
                $table->foreignId('ticket_lot_id')->constrained('ticket_lots', 'id', 'tkt_lot_fk');
                $table->string('price_kind', 12);
                // Preço do lote, desconto do cupom e o que foi cobrado (amount).
                $table->decimal('list_price', 10, 2)->default(0);
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->decimal('amount', 10, 2);
                $table->string('holder_name');
                $table->string('holder_cpf', 11);
                $table->string('code', 20)->unique('tkt_code_uq');
                $table->string('status', 20)->default('pending');
                $table->dateTime('checked_in_at')->nullable();
                $table->foreignId('checked_in_by')->nullable()->constrained('users', 'id', 'tkt_checker_fk');
                $table->string('checkin_source', 20)->nullable();
                $table->decimal('refunded_amount', 10, 2)->default(0);
                $table->dateTime('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users', 'id', 'tkt_canceller_fk');
                $table->string('cancel_reason', 500)->nullable();
                $table->timestamps();

                $table->index(['ticket_event_id', 'holder_cpf'], 'tkt_event_cpf_idx');
                $table->index(['ticket_lot_id', 'status'], 'tkt_lot_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_payments');
        Schema::dropIfExists('ticket_orders');
        Schema::dropIfExists('ticket_coupons');
        Schema::dropIfExists('ticket_lots');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('ticket_events');
    }
};
