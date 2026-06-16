<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->string('promo_code_used', 80)->nullable();
                $table->string('full_name', 140);
                $table->string('profile_image_path')->nullable();
                $table->string('phone_country', 8);
                $table->string('phone_number', 32);
                $table->string('phone_e164', 24)->unique();
                $table->string('password_hash');
                $table->boolean('active')->default(true)->index();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
