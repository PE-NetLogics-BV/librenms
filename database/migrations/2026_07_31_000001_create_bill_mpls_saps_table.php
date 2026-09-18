<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bill_mpls_saps', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('bill_id');
            $table->unsignedInteger('sap_id');
            $table->unique(['bill_id', 'sap_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('bill_mpls_saps');
    }
};
