<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('chat_message_reactions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            // Одна реакция от пользователя на сообщение: новая заменяет прежнюю
            $table->unique(['message_id', 'user_id']);
        });

        // Папки личные: у каждого пользователя свой набор, собеседник их не видит
        Schema::create('chat_folders', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 32);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('chat_folder_chats', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_id')->constrained('chat_folders')->cascadeOnDelete();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['folder_id', 'chat_id']);
            $table->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_folder_chats');
        Schema::dropIfExists('chat_folders');
        Schema::dropIfExists('chat_message_reactions');
    }
};
