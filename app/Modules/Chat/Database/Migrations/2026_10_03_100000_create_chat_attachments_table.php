<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('chat_attachments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Файл загружают до сообщения: пока его не отправили, message_id пустой
            $table->foreignId('message_id')->nullable()->constrained('chat_messages')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('status', 16);
            $table->string('original_name');
            $table->string('mime', 127);
            // Сколько занимает на диске вместе с превью: из суммы считается лимит хранилища
            $table->unsignedBigInteger('size');
            $table->string('path')->unique();
            $table->string('thumb_path')->nullable()->unique();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('waveform')->nullable();
            $table->timestamps();

            // Поиск неотправленных вложений для очистки
            $table->index(['message_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
    }
};
