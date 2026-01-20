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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('fpl_id')->unique();
            $table->unsignedInteger('code');
            $table->string('name');
            $table->string('short_name', 3);
            $table->string('logo_url')->nullable();
            $table->unsignedInteger('strength');
            $table->unsignedInteger('position')->nullable();
            $table->unsignedInteger('played')->default(0);
            $table->unsignedInteger('win')->default(0);
            $table->unsignedInteger('draw')->default(0);
            $table->unsignedInteger('loss')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->string('form')->nullable();
            $table->boolean('unavailable')->default(false);
            
            // Strength ratings
            $table->unsignedInteger('strength_overall_home')->nullable();
            $table->unsignedInteger('strength_overall_away')->nullable();
            $table->unsignedInteger('strength_attack_home')->nullable();
            $table->unsignedInteger('strength_attack_away')->nullable();
            $table->unsignedInteger('strength_defence_home')->nullable();
            $table->unsignedInteger('strength_defence_away')->nullable();
            
            $table->unsignedInteger('pulse_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('name');
            $table->index('short_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
