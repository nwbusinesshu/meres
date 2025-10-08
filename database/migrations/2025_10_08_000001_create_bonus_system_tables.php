<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Table 1: user_wages - Current wage data per employee
        Schema::create('user_wages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id');
            $table->decimal('net_wage', 12, 2);
            $table->string('currency', 3)->default('HUF');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->primary(['user_id', 'organization_id']);
            $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
            $table->foreign('organization_id')->references('id')->on('organization')->onDelete('cascade');
            $table->index('organization_id', 'idx_org');
        });

        // Table 2: bonus_malus_config - Organization-specific multiplier configuration
        Schema::create('bonus_malus_config', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id');
            $table->smallInteger('level'); // 1-15 (M04=1, M03=2, ... B10=15)
            $table->decimal('multiplier', 5, 2);
            
            $table->primary(['organization_id', 'level']);
            $table->foreign('organization_id')->references('id')->on('organization')->onDelete('cascade');
        });

        // Table 3: assessment_bonuses - Calculated bonuses per assessment per user
        Schema::create('assessment_bonuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('user_id');
            $table->smallInteger('bonus_malus_level');
            $table->decimal('net_wage', 12, 2);
            $table->string('currency', 3)->default('HUF');
            $table->decimal('multiplier', 5, 2);
            $table->decimal('bonus_amount', 12, 2);
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['assessment_id', 'user_id'], 'unique_assessment_user');
            $table->foreign('assessment_id')->references('id')->on('assessment')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
            $table->index('assessment_id', 'idx_assessment');
            $table->index(['assessment_id', 'is_paid'], 'idx_paid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assessment_bonuses');
        Schema::dropIfExists('bonus_malus_config');
        Schema::dropIfExists('user_wages');
    }
};