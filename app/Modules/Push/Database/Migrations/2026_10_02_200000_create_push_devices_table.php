<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('push_devices', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Выход из аккаунта удаляет токен входа, а вместе с ним и устройство: push туда больше не идут
            $table->foreignId('access_token_id')->nullable()->constrained('personal_access_tokens')->cascadeOnDelete();
            // Токен FCM; один телефон — одна запись, при входе другим пользователем она переходит к нему
            $table->string('token')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
