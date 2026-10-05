<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', static function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('forwarded_from_name');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', static function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });
    }
};
