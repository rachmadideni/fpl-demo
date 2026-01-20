<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('element_type')->unique(); // 1, 2, 3, 4
            $table->string('singular_name'); // Goalkeeper, Defender, Midfielder, Forward
            $table->string('singular_name_short'); // GKP, DEF, MID, FWD
            $table->string('plural_name'); // Goalkeepers, Defenders, Midfielders, Forwards
            $table->string('plural_name_short'); // GKP, DEF, MID, FWD
            $table->unsignedInteger('squad_select'); // How many can select in squad
            $table->unsignedInteger('squad_min_select'); // Minimum in squad
            $table->unsignedInteger('squad_max_select'); // Maximum in squad
            $table->unsignedInteger('squad_min_play'); // Minimum in starting 11
            $table->unsignedInteger('squad_max_play'); // Maximum in starting 11
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
