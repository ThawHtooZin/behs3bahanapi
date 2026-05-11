<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes membership fee / members tables (feature reverted for redesign).
     */
    public function up(): void
    {
        Schema::dropIfExists('membership_fee_submissions');
        Schema::dropIfExists('members');
    }

    /**
     * No-op: restoring these tables would require the original create migrations.
     */
    public function down(): void
    {
        //
    }
};
