<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        // Перевод сообщения на язык интерфейса участника: один на язык, а не на получателя
        Schema::create('chat_message_translations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->text('body');
            $table->timestamps();

            $table->unique(['message_id', 'locale']);
        });

        Schema::table('chat_messages', static function (Blueprint $table): void {
            // Язык текста, как его определила нейросеть; null — ещё не переводили
            $table->string('body_locale', 8)->nullable()->after('body');
        });

        Schema::table('chats', static function (Blueprint $table): void {
            // Кто кем друг другу приходится: переводчик выбирает по ней обращения (anh/em, ты/вы)
            $table->string('translation_note', 500)->nullable()->after('last_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('chats', static function (Blueprint $table): void {
            $table->dropColumn('translation_note');
        });

        Schema::table('chat_messages', static function (Blueprint $table): void {
            $table->dropColumn('body_locale');
        });

        Schema::dropIfExists('chat_message_translations');
    }
};
