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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            
            // FPL Identifiers
            $table->unsignedBigInteger('fpl_id')->unique();
            $table->unsignedBigInteger('code');
            $table->string('opta_code')->nullable();
            
            // Basic Information
            $table->string('web_name');
            $table->string('first_name');
            $table->string('second_name');
            $table->string('photo')->nullable();
            $table->unsignedInteger('squad_number')->nullable();
            
            // Team & Position
            $table->unsignedInteger('team_id');
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('element_type'); // Keep for reference: 1=GK, 2=DEF, 3=MID, 4=FWD
            
            // Status
            $table->string('status', 1)->default('a'); // a=available, d=doubtful, i=injured, u=unavailable
            $table->text('news')->nullable();
            $table->timestamp('news_added')->nullable();
            $table->unsignedInteger('chance_of_playing_this_round')->nullable();
            $table->unsignedInteger('chance_of_playing_next_round')->nullable();
            
            // Pricing
            $table->unsignedInteger('now_cost'); // Price in tenths (e.g., 59 = £5.9m)
            $table->integer('cost_change_start')->default(0);
            $table->integer('cost_change_event')->default(0);
            
            // Performance Stats
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('event_points')->default(0);
            $table->decimal('points_per_game', 4, 1)->default(0);
            $table->decimal('form', 4, 1)->default(0);
            $table->decimal('selected_by_percent', 5, 1)->default(0);
            
            // Match Statistics
            $table->unsignedInteger('minutes')->default(0);
            $table->unsignedInteger('goals_scored')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('clean_sheets')->default(0);
            $table->unsignedInteger('goals_conceded')->default(0);
            $table->unsignedInteger('own_goals')->default(0);
            $table->unsignedInteger('penalties_saved')->default(0);
            $table->unsignedInteger('penalties_missed')->default(0);
            $table->unsignedInteger('yellow_cards')->default(0);
            $table->unsignedInteger('red_cards')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->unsignedInteger('bonus')->default(0);
            $table->unsignedInteger('bps')->default(0); // Bonus Points System
            
            // Advanced Metrics
            $table->decimal('influence', 8, 1)->default(0);
            $table->decimal('creativity', 8, 1)->default(0);
            $table->decimal('threat', 8, 1)->default(0);
            $table->decimal('ict_index', 8, 1)->default(0);
            $table->decimal('expected_goals', 8, 2)->default(0);
            $table->decimal('expected_assists', 8, 2)->default(0);
            $table->decimal('expected_goal_involvements', 8, 2)->default(0);
            $table->decimal('expected_goals_conceded', 8, 2)->default(0);
            
            // Transfers
            $table->unsignedBigInteger('transfers_in')->default(0);
            $table->unsignedBigInteger('transfers_out')->default(0);
            $table->unsignedInteger('transfers_in_event')->default(0);
            $table->unsignedInteger('transfers_out_event')->default(0);
            
            // Flags
            $table->boolean('in_dreamteam')->default(false);
            $table->unsignedInteger('dreamteam_count')->default(0);
            $table->boolean('special')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index('team_id');
            $table->index('element_type');
            $table->index('now_cost');
            $table->index('total_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
