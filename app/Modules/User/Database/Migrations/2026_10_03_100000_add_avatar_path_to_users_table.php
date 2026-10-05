<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            // Путь на диске вложений: по нему Caddy отдаёт файл после проверки подписи
            $table->string('avatar_path')->nullable()->unique()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropUnique(['avatar_path']);
            $table->dropColumn('avatar_path');
        });
    }
};
