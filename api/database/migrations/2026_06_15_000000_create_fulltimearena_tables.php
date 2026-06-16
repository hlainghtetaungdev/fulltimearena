<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('staff_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('role', 24)->default('agent')->index();
            $table->string('username', 80)->unique();
            $table->string('display_name', 140)->nullable();
            $table->string('password_hash');
            $table->string('promo_code', 80)->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        $this->create('settings', function (Blueprint $table) {
            $table->string('setting_key', 80)->primary();
            $table->mediumText('setting_value')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        $this->create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->string('link_url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        $this->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('link_url', 500)->nullable();
            $table->string('icon_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        $this->create('agent_category_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->index();
            $table->unsignedBigInteger('category_id');
            $table->boolean('active')->default(true);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['agent_id', 'category_id']);
        });

        $this->create('live_matches', function (Blueprint $table) {
            $table->id();
            $table->string('league_name', 180)->nullable();
            $table->string('match_time', 80)->nullable();
            $table->string('status_text', 80)->nullable();
            $table->boolean('is_live')->default(true);
            $table->string('home_name', 160);
            $table->string('home_logo', 500)->nullable();
            $table->string('away_name', 160);
            $table->string('away_logo', 500)->nullable();
            $table->mediumText('streams_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $this->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('title', 180);
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        $this->create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('storage_id', 128)->nullable()->unique();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('os_version', 160)->nullable();
            $table->string('browser_language', 80)->nullable();
            $table->string('screen_size', 80)->nullable();
            $table->mediumText('device_json')->nullable();
            $table->string('ht_result', 50);
            $table->string('ft_result', 50);
            $table->string('first_scorer', 120);
            $table->string('wallet_type', 30);
            $table->string('wallet_name', 120);
            $table->string('wallet_number', 80);
            $table->string('result_status', 16)->default('pending')->index();
            $table->boolean('is_winner')->default(false)->index();
            $table->timestamp('created_at')->useCurrent();
        });

        $this->create('unit_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->unsignedBigInteger('game_account_id')->nullable();
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->string('request_type', 16)->index();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('status', 16)->default('pending')->index();
            $table->string('proof_path')->nullable();
            $table->mediumText('request_data')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('review_token', 64)->nullable();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_agent_id')->nullable();
            $table->timestamps();
        });

        $this->create('agent_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->index();
            $table->string('method', 40);
            $table->string('account_name', 120);
            $table->string('account_number', 80);
            $table->string('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $this->create('user_game_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->string('provider_key', 60);
            $table->string('provider_label', 120);
            $table->string('external_username', 120);
            $table->unsignedBigInteger('external_member_id')->nullable();
            $table->string('external_password_enc', 512)->nullable();
            $table->string('username_suffix', 30)->nullable();
            $table->string('download_url')->nullable();
            $table->mediumText('api_payload')->nullable();
            $table->mediumText('api_response')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'provider_key']);
        });

        $this->create('agent_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('provider_key', 60);
            $table->string('provider_label', 120);
            $table->string('agent_username_enc', 512);
            $table->string('agent_password_enc', 512);
            $table->unsignedInteger('bet_limit_single')->default(0);
            $table->unsignedInteger('bet_limit_mix')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['agent_id', 'provider_key']);
        });

        $this->create('agent_provider_health_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('provider_key', 60);
            $table->string('status', 32)->default('not_configured');
            $table->string('message')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
            $table->unique(['agent_id', 'provider_key']);
        });

        $this->create('agent_contact_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->unique();
            $table->string('phone', 80)->nullable();
            $table->string('viber', 120)->nullable();
            $table->string('telegram', 160)->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();
            $table->timestamps();
        });

        $this->create('agent_ibet_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->unique();
            $table->mediumText('football_rules');
            $table->mediumText('egame_rules');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        $this->create('agent_ibet_rule_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->index();
            $table->mediumText('football_rules');
            $table->mediumText('egame_rules');
            $table->timestamp('created_at')->useCurrent();
        });

        $this->create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['personal_access_tokens', 'agent_ibet_rule_history', 'agent_ibet_rules', 'agent_contact_profiles', 'agent_provider_health_checks', 'agent_provider_configs', 'user_game_accounts', 'agent_payment_accounts', 'unit_requests', 'submissions', 'notifications', 'live_matches', 'agent_category_permissions', 'categories', 'ads', 'settings', 'staff_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function create(string $table, callable $callback): void
    {
        if (!Schema::hasTable($table)) {
            Schema::create($table, $callback);
        }
    }
};
