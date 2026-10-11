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
        Schema::table('users', function (Blueprint $table) {
            $table->string('type', 16)->default('citizen')->after('id');
            $table->string('national_id', 11)->nullable()->unique()->after('type');
            $table->string('name_ar')->nullable()->after('name');
            $table->foreignId('gov_entity_id')->nullable()->after('national_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gov_entity_id');
            $table->dropUnique(['national_id']);
            $table->dropColumn(['type', 'national_id', 'name_ar']);
        });
    }
};
