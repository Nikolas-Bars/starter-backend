<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('chats', static function (Blueprint $table): void {
            $table->id();
            // Пока только личные чаты; групповые появятся новым типом, участники уже в chat_members
            $table->string('type', 16)->default('direct');
            // Личный чат с парой пользователей один: «меньший id:больший id»
            $table->string('direct_key', 64)->nullable()->unique();
            // Чаты в списке упорядочены по последнему сообщению: id сообщений растут со временем
            $table->unsignedBigInteger('last_message_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('chat_members', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Всё до этого сообщения включительно участник прочитал; 0 — ничего
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['chat_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('chat_messages', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Клиент сам выбирает id сообщения: повтор запроса после обрыва связи не создаст дубль
            $table->uuid('client_id');
            $table->text('body');
            $table->timestamps();

            $table->unique(['user_id', 'client_id']);
            $table->index(['chat_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_members');
        Schema::dropIfExists('chats');
    }
};
