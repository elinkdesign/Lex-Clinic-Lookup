<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('shortlists')) {
            Schema::create('shortlists', function (Blueprint $table) {
                $table->id();
                $table->string('NID')->unique();
                $table->string('LIC')->unique();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('longlists')) {
            Schema::create('longlists', function (Blueprint $table) {
                $table->id();
                $table->char('NID', 4)->unique();
                $table->string('LIC')->unique();
                $table->string('name');
                $table->timestamps();
            });
        }

        // Check if the constraint exists before adding it
        $constraintExists = DB::select("SELECT COUNT(*) as count FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'longlists' AND constraint_name = 'check_nid_numeric'");

        if ($constraintExists[0]->count == 0) {
            DB::statement('ALTER TABLE longlists ADD CONSTRAINT check_nid_numeric CHECK (NID REGEXP \'^[0-9]{4}$\')');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shortlists');
        Schema::dropIfExists('longlists');
    }
};
