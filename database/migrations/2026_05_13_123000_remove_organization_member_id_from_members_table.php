<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('members') || !Schema::hasColumn('members', 'organization_member_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement('
                CREATE TABLE members_tmp (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    user_id INTEGER NOT NULL,
                    name VARCHAR NOT NULL,
                    nrc_number VARCHAR NOT NULL,
                    gender VARCHAR NOT NULL,
                    image VARCHAR NULL,
                    dob DATE NOT NULL,
                    address TEXT NOT NULL,
                    contact_number VARCHAR NOT NULL,
                    email VARCHAR NOT NULL,
                    parent_name VARCHAR NULL,
                    parent_occupation VARCHAR NULL,
                    agreed_to_rules TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR CHECK(status IN (\'pending\', \'approved\', \'rejected\')) NOT NULL DEFAULT \'pending\',
                    approved_at DATETIME NULL,
                    approved_by INTEGER NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
                )
            ');

            DB::statement('
                INSERT INTO members_tmp (
                    id, user_id, name, nrc_number, gender, image, dob, address,
                    contact_number, email, parent_name, parent_occupation,
                    agreed_to_rules, status, approved_at, approved_by, created_at, updated_at
                )
                SELECT
                    id, user_id, name, nrc_number, gender, image, dob, address,
                    contact_number, email, parent_name, parent_occupation,
                    agreed_to_rules, status, approved_at, approved_by, created_at, updated_at
                FROM members
            ');

            DB::statement('DROP TABLE members');
            DB::statement('ALTER TABLE members_tmp RENAME TO members');
            DB::statement('CREATE UNIQUE INDEX members_user_id_unique ON members(user_id)');
            DB::statement('CREATE UNIQUE INDEX members_nrc_number_unique ON members(nrc_number)');
            DB::statement('PRAGMA foreign_keys = ON');
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_member_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('members') || Schema::hasColumn('members', 'organization_member_id')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_member_id')->nullable();
        });
    }
};
