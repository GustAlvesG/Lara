<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de espaço social: pré-reserva → análise da equipe → pagamento → confirmada.
 *
 * A ocupação continua sendo um `schedules` (o dia inteiro), para a colisão, a
 * agenda, o pagamento e o estorno seguirem pelo caminho de sempre. Aqui fica o
 * que só o espaço social tem: o estágio do fluxo, os dados do evento, o valor
 * (preço do espaço + convidados extras + bebidas − desconto), o contrato, a aprovação e o prazo
 * para pagar.
 *
 * Itens e convidados guardam cópia do que foi escolhido (nome, código MV,
 * preço): mudar o catálogo depois não muda o que foi contratado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->unique()->constrained('schedules');
            $table->foreignId('place_id')->constrained('places');
            $table->unsignedBigInteger('member_id')->index();
            $table->date('event_date')->index();
            $table->string('stage', 20)->index();

            $table->string('event_type', 120);
            $table->unsignedInteger('expected_guests')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('base_price', 10, 2);
            $table->unsignedInteger('extra_guests')->default(0);
            $table->decimal('extra_guest_price', 10, 2)->default(0);
            $table->decimal('extras_total', 10, 2)->default(0);
            $table->decimal('items_total', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('total', 10, 2);

            $table->timestamp('contract_ok_at')->nullable();
            $table->unsignedBigInteger('contract_ok_by')->nullable();
            $table->string('contract_file')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('payment_due_at')->nullable();

            $table->timestamp('refused_at')->nullable();
            $table->unsignedBigInteger('refused_by')->nullable();
            $table->text('refusal_reason')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->unsignedBigInteger('created_by_user')->nullable();
            $table->timestamps();
        });

        Schema::create('social_booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_booking_id')->constrained('social_bookings')->cascadeOnDelete();
            $table->foreignId('social_beverage_id')->nullable()->constrained('social_beverages')->nullOnDelete();
            $table->string('name');
            $table->string('unit', 60)->nullable();
            $table->string('mv_code', 60)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });

        Schema::create('social_booking_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_booking_id')->constrained('social_bookings')->cascadeOnDelete();
            $table->string('name');
            $table->string('cpf', 11);
            $table->unsignedBigInteger('one_off_access_id')->nullable();
            $table->timestamps();

            $table->unique(['social_booking_id', 'cpf']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_booking_guests');
        Schema::dropIfExists('social_booking_items');
        Schema::dropIfExists('social_bookings');
    }
};
