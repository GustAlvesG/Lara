<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torneios (módulo novo, 22/10/2026). Regras e modelagem em
 * docs/funcionalidades/torneios.md.
 *
 * Situações gravadas como texto (constantes nos models), como nos espaços
 * sociais. FKs e índices com nome curto e explícito: o nome automático de
 * `tournament_team_invites_invited_by_member_id_foreign` e parecidos passa
 * dos 64 caracteres do MySQL (o SQLite dos testes não reclama).
 *
 * Cada tabela só é criada se ainda não existir, para o deploy poder rodar de
 * novo depois de uma falha no meio.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de categorias de cada grupo de espaços (o "book" do grupo),
        // reaproveitado entre torneios.
        if (! Schema::hasTable('tournament_category_templates')) {
            Schema::create('tournament_category_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('place_group_id')->constrained('place_groups', 'id', 'tct_group_fk');
                $table->string('name');
                $table->unsignedTinyInteger('team_size')->default(1);
                $table->string('gender', 10)->default('any');
                $table->unsignedTinyInteger('min_age')->nullable();
                $table->unsignedTinyInteger('max_age')->nullable();
                $table->boolean('active')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tournaments')) {
            Schema::create('tournaments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('place_group_id')->constrained('place_groups', 'id', 'tour_group_fk');
                $table->string('title');
                $table->text('description')->nullable();
                $table->longText('rules')->nullable();
                $table->string('image')->nullable();
                $table->dateTime('registration_starts_at');
                $table->dateTime('registration_ends_at');
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('audience', 20)->default('members');
                $table->string('status', 20)->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'tour_creator_fk');
                $table->softDeletes();
                $table->timestamps();

                $table->index('status', 'tour_status_idx');
            });
        }

        // A categoria ligada ao torneio. Nome, tamanho, sexo e idade são
        // copiados do catálogo: mudar o catálogo não mexe em torneio já criado.
        if (! Schema::hasTable('tournament_categories')) {
            Schema::create('tournament_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained('tournaments', 'id', 'tcat_tour_fk')->cascadeOnDelete();
                $table->foreignId('category_template_id')->nullable()->constrained('tournament_category_templates', 'id', 'tcat_template_fk')->nullOnDelete();
                $table->string('name');
                $table->unsignedTinyInteger('team_size');
                $table->string('gender', 10);
                $table->unsignedTinyInteger('min_age')->nullable();
                $table->unsignedTinyInteger('max_age')->nullable();
                $table->decimal('price', 10, 2);
                $table->unsignedSmallInteger('max_registrations')->nullable();
                $table->string('team_formation', 20);
                $table->string('format', 30);
                $table->unsignedTinyInteger('groups_count')->nullable();
                $table->unsignedTinyInteger('qualifiers_per_group')->nullable();
                $table->boolean('third_place_match')->default(false);
                $table->unsignedTinyInteger('sets_to_win')->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tournament_id', 'category_template_id'], 'tcat_tour_template_uq');
            });
        }

        // Pagamento na eRede (ou baixa manual). Um pagamento pode cobrir várias
        // inscrições da mesma pessoa. Mesmo desenho do SchedulePayment.
        if (! Schema::hasTable('tournament_payments')) {
            Schema::create('tournament_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->constrained('members', 'id', 'tpay_member_fk');
                $table->string('payment_method', 40);
                $table->decimal('paid_amount', 10, 2);
                $table->dateTime('paid_at')->nullable();
                $table->string('payment_integration_id')->nullable();
                $table->string('status', 20)->default('paid');
                $table->decimal('refunded_amount', 10, 2)->default(0);
                $table->foreignId('refunded_by')->nullable()->constrained('users', 'id', 'tpay_refunder_fk');
                $table->dateTime('refunded_at')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->foreignId('recorded_by')->nullable()->constrained('users', 'id', 'tpay_recorder_fk');
                $table->string('manual_notes', 500)->nullable();
                $table->timestamps();

                $table->index('payment_integration_id', 'tpay_integration_idx');
            });
        }

        // A inscrição individual: uma pessoa numa categoria. Os dados da
        // pessoa são copiados do MultiClubes no momento da inscrição.
        if (! Schema::hasTable('tournament_registrations')) {
            Schema::create('tournament_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_category_id')->constrained('tournament_categories', 'id', 'treg_cat_fk');
                $table->foreignId('member_id')->constrained('members', 'id', 'treg_member_fk');
                $table->string('cpf', 11);
                $table->string('title', 20)->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->date('birth_date')->nullable();
                $table->string('gender', 10)->nullable();
                $table->decimal('amount', 10, 2);
                $table->string('status', 20)->default('pending_payment');
                $table->foreignId('eligibility_override_by')->nullable()->constrained('users', 'id', 'treg_override_fk');
                $table->string('eligibility_override_reason', 500)->nullable();
                $table->foreignId('tournament_payment_id')->nullable()->constrained('tournament_payments', 'id', 'treg_payment_fk')->nullOnDelete();
                $table->dateTime('paid_at')->nullable();
                $table->dateTime('withdrawn_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users', 'id', 'treg_canceller_fk');
                $table->string('cancel_reason', 500)->nullable();
                $table->timestamps();

                $table->index(['tournament_category_id', 'member_id'], 'treg_cat_member_idx');
                $table->index('cpf', 'treg_cpf_idx');
            });
        }

        if (! Schema::hasTable('tournament_teams')) {
            Schema::create('tournament_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_category_id')->constrained('tournament_categories', 'id', 'tteam_cat_fk')->cascadeOnDelete();
                $table->string('name');
                $table->foreignId('captain_registration_id')->nullable()->constrained('tournament_registrations', 'id', 'tteam_captain_fk')->nullOnDelete();
                $table->string('origin', 20);
                $table->string('status', 20)->default('forming');
                $table->unsignedSmallInteger('seed')->nullable();
                $table->timestamps();

                $table->unique(['tournament_category_id', 'name'], 'tteam_cat_name_uq');
            });
        }

        // Quem está em cada time. Uma inscrição fica em um time só: sair do
        // time apaga a linha.
        if (! Schema::hasTable('tournament_team_members')) {
            Schema::create('tournament_team_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_team_id')->constrained('tournament_teams', 'id', 'ttm_team_fk')->cascadeOnDelete();
                $table->foreignId('tournament_registration_id')->constrained('tournament_registrations', 'id', 'ttm_reg_fk')->cascadeOnDelete();
                $table->foreignId('added_by_user_id')->nullable()->constrained('users', 'id', 'ttm_user_fk');
                $table->foreignId('added_by_member_id')->nullable()->constrained('members', 'id', 'ttm_member_fk');
                $table->timestamps();

                $table->unique('tournament_registration_id', 'ttm_reg_uq');
            });
        }

        // Convite do capitão (categorias com montagem pelo capitão). Guarda o
        // CPF: o convidado pode ainda não ter cadastro no site.
        if (! Schema::hasTable('tournament_team_invites')) {
            Schema::create('tournament_team_invites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_team_id')->constrained('tournament_teams', 'id', 'tinv_team_fk')->cascadeOnDelete();
                $table->foreignId('invited_by_member_id')->constrained('members', 'id', 'tinv_inviter_fk');
                $table->string('cpf', 11);
                $table->string('title', 20)->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('tournament_registration_id')->nullable()->constrained('tournament_registrations', 'id', 'tinv_reg_fk')->nullOnDelete();
                $table->dateTime('responded_at')->nullable();
                $table->timestamps();

                $table->index('cpf', 'tinv_cpf_idx');
            });
        }

        if (! Schema::hasTable('tournament_stages')) {
            Schema::create('tournament_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_category_id')->constrained('tournament_categories', 'id', 'tstage_cat_fk')->cascadeOnDelete();
                $table->string('type', 20);
                $table->unsignedTinyInteger('order')->default(1);
                $table->string('generation', 10)->nullable();
                $table->dateTime('generated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tournament_groups')) {
            Schema::create('tournament_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_stage_id')->constrained('tournament_stages', 'id', 'tgroup_stage_fk')->cascadeOnDelete();
                $table->string('name', 40);
                $table->unsignedTinyInteger('order')->default(1);
                $table->timestamps();
            });
        }

        // `final_position` é a ordem que o organizador define ao fechar o
        // grupo: empate na classificação é resolvido fora do sistema.
        if (! Schema::hasTable('tournament_group_teams')) {
            Schema::create('tournament_group_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_group_id')->constrained('tournament_groups', 'id', 'tgt_group_fk')->cascadeOnDelete();
                $table->foreignId('tournament_team_id')->constrained('tournament_teams', 'id', 'tgt_team_fk')->cascadeOnDelete();
                $table->unsignedTinyInteger('final_position')->nullable();
                $table->timestamps();

                $table->unique(['tournament_group_id', 'tournament_team_id'], 'tgt_group_team_uq');
            });
        }

        // Um jogo. No mata-mata, o vencedor segue para `next_match_id` no lado
        // `next_slot`; um lado vindo da fase de grupos diz de onde vem
        // (`*_source_group_id` + `*_source_position`, ex.: 1º do Grupo A).
        if (! Schema::hasTable('tournament_matches')) {
            Schema::create('tournament_matches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_stage_id')->constrained('tournament_stages', 'id', 'tmatch_stage_fk')->cascadeOnDelete();
                $table->foreignId('tournament_group_id')->nullable()->constrained('tournament_groups', 'id', 'tmatch_group_fk')->cascadeOnDelete();
                $table->unsignedTinyInteger('round');
                $table->unsignedSmallInteger('position');
                $table->foreignId('home_team_id')->nullable()->constrained('tournament_teams', 'id', 'tmatch_home_fk')->nullOnDelete();
                $table->foreignId('away_team_id')->nullable()->constrained('tournament_teams', 'id', 'tmatch_away_fk')->nullOnDelete();
                $table->foreignId('next_match_id')->nullable()->constrained('tournament_matches', 'id', 'tmatch_next_fk')->nullOnDelete();
                $table->string('next_slot', 4)->nullable();
                $table->foreignId('home_source_group_id')->nullable()->constrained('tournament_groups', 'id', 'tmatch_hsrc_fk')->nullOnDelete();
                $table->unsignedTinyInteger('home_source_position')->nullable();
                $table->foreignId('away_source_group_id')->nullable()->constrained('tournament_groups', 'id', 'tmatch_asrc_fk')->nullOnDelete();
                $table->unsignedTinyInteger('away_source_position')->nullable();
                $table->dateTime('scheduled_at')->nullable();
                $table->string('location_note')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('home_sets')->nullable();
                $table->unsignedTinyInteger('away_sets')->nullable();
                $table->foreignId('winner_team_id')->nullable()->constrained('tournament_teams', 'id', 'tmatch_winner_fk')->nullOnDelete();
                $table->boolean('locked_by_lara')->default(false);
                $table->string('result_origin', 10)->nullable();
                $table->foreignId('result_user_id')->nullable()->constrained('users', 'id', 'tmatch_user_fk');
                $table->dateTime('result_at')->nullable();
                $table->timestamps();

                $table->index(['tournament_stage_id', 'round', 'position'], 'tmatch_stage_round_idx');
            });
        }

        if (! Schema::hasTable('tournament_match_sets')) {
            Schema::create('tournament_match_sets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_match_id')->constrained('tournament_matches', 'id', 'tset_match_fk')->cascadeOnDelete();
                $table->unsignedTinyInteger('set_number');
                $table->unsignedTinyInteger('home_games');
                $table->unsignedTinyInteger('away_games');
                $table->unsignedTinyInteger('home_tiebreak')->nullable();
                $table->unsignedTinyInteger('away_tiebreak')->nullable();
                $table->timestamps();

                $table->unique(['tournament_match_id', 'set_number'], 'tset_match_number_uq');
            });
        }

        // Cada resultado lançado ou corrigido, no Lara ou no site.
        if (! Schema::hasTable('tournament_match_result_logs')) {
            Schema::create('tournament_match_result_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_match_id')->constrained('tournament_matches', 'id', 'tlog_match_fk')->cascadeOnDelete();
                $table->string('origin', 10);
                $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'tlog_user_fk');
                $table->json('payload');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_match_result_logs');
        Schema::dropIfExists('tournament_match_sets');
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_group_teams');
        Schema::dropIfExists('tournament_groups');
        Schema::dropIfExists('tournament_stages');
        Schema::dropIfExists('tournament_team_invites');
        Schema::dropIfExists('tournament_team_members');
        Schema::dropIfExists('tournament_teams');
        Schema::dropIfExists('tournament_registrations');
        Schema::dropIfExists('tournament_payments');
        Schema::dropIfExists('tournament_categories');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('tournament_category_templates');
    }
};
