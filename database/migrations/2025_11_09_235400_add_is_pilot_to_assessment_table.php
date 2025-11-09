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
        Schema::table('assessment', function (Blueprint $table) {
            // Add is_pilot column after closed_at
            $table->boolean('is_pilot')
                  ->default(false)
                  ->after('closed_at')
                  ->comment('Pilot assessment - evaluations run without bonus/malus changes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assessment', function (Blueprint $table) {
            $table->dropColumn('is_pilot');
        });
    }
};