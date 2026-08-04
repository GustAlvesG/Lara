<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema mínimo do módulo de freelancers para os testes de Pix.
 *
 * Sem `RefreshDatabase` pelo mesmo motivo já registrado em
 * `LaraMessageHistoryTest`: a cadeia completa de migrations não roda hoje
 * (`add_columns_member` e `tourments` adicionam `members.title` as duas), e
 * algumas migrations do módulo usam sintaxe de MySQL e chaves para `users` —
 * tabela cuja model fixa a conexão `mysql` e que não existe no SQLite da suíte.
 *
 * As colunas aqui espelham o que os models leem. A migration de `pix_payments`
 * é a de verdade, aplicada a partir do arquivo — ela é parte do que está sendo
 * testado, e uma cópia dela aqui deixaria de acusar uma divergência.
 */
trait CreatesFreelancerPixSchema
{
    protected function createFreelancerPixSchema(): void
    {
        Schema::create('freelancers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cpf');
            $table->string('pix_key')->nullable();
            $table->string('rg')->nullable();
            $table->string('email')->nullable();
            $table->string('nacionality')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('address')->nullable();
            $table->string('telephone')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('function_freelancers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });

        Schema::create('freelancer_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('freelancer_id');
            $table->unsignedBigInteger('function_freelancer_id');
            $table->unsignedBigInteger('parent_service_id')->nullable();
            $table->unsignedBigInteger('amendment_service_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();

            $table->date('start_date');
            $table->time('start_time');
            $table->date('end_date');
            $table->time('end_time');
            $table->decimal('price', 8, 2);
            $table->decimal('total_hours', 8, 2);
            $table->integer('status_id')->default(1);

            $table->dateTime('freelancer_signed_at')->nullable();
            $table->unsignedBigInteger('freelancer_signed_by')->nullable();
            $table->dateTime('coordinator_signed_at')->nullable();
            $table->unsignedBigInteger('coordinator_signed_by')->nullable();

            $table->dateTime('manager_approved_at')->nullable();
            $table->unsignedBigInteger('manager_approved_by')->nullable();
            $table->dateTime('manager_rejected_at')->nullable();
            $table->unsignedBigInteger('manager_rejected_by')->nullable();
            $table->dateTime('director_approved_at')->nullable();
            $table->dateTime('director_rejected_at')->nullable();

            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();

            $table->dateTime('amended_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->dateTime('weekly_limit_authorized_at')->nullable();
            $table->unsignedBigInteger('weekly_limit_authorized_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });

        (require base_path('database/migrations/2026_08_04_140000_create_pix_payments_table.php'))->up();
    }
}
