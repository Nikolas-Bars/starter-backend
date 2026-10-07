<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            // Язык интерфейса; на него же переводятся входящие сообщения
            $table->string('locale', 8)->default('ru')->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
