<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff who sign in with Entra ID are linked by their Entra object ID (the `oid` claim)
     * and have no password stored in Sahl.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('entra_object_id')->nullable()->unique()->after('national_id');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['entra_object_id']);
            $table->dropColumn('entra_object_id');
        });
    }
};
