<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forwarding_rules', function (Blueprint $table) {
            $table->string('subject_contains')->nullable()->after('checks_invoice_link');
        });
    }

    public function down(): void
    {
        Schema::table('forwarding_rules', function (Blueprint $table) {
            $table->dropColumn('subject_contains');
        });
    }
};
