<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('vpn_profiles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64);
            // Ссылка (vless://, trojan://, ss://…) или JSON исходящих Xray с общим ключом; зашифрована (cast encrypted)
            $table->text('uri');
            // Порядок в списке и при переборе «по очереди»: меньше — раньше
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_profiles');
    }
};
