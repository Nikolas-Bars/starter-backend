<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', static function (Blueprint $table): void {
            // Автор оригинала: имя запоминаем на момент пересылки — оно остаётся, даже если автора удалят
            $table->foreignId('forwarded_from_user_id')->nullable()->after('body')->constrained('users')->nullOnDelete();
            $table->string('forwarded_from_name')->nullable()->after('forwarded_from_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('forwarded_from_user_id');
            $table->dropColumn('forwarded_from_name');
        });
    }
};
