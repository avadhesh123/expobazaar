<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $columns = [
            ['street_address', "VARCHAR(500) NULL"],
            ['province_state', "VARCHAR(255) NULL"],
            ['finance_contact_person', "VARCHAR(255) NULL"],
            ['landline', "VARCHAR(50) NULL"],
            ['official_website', "VARCHAR(500) NULL"],
            ['iec_code', "VARCHAR(50) NULL"],
            ['msme_number', "VARCHAR(50) NULL"],
        ];

        foreach ($columns as [$col, $def]) {
            $exists = DB::select("SHOW COLUMNS FROM `vendors` WHERE Field = '{$col}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `vendors` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    public function down(): void
    {
        $columns = ['street_address', 'province_state', 'finance_contact_person', 'landline', 'official_website', 'iec_code', 'msme_number'];
        foreach ($columns as $col) {
            $exists = DB::select("SHOW COLUMNS FROM `vendors` WHERE Field = '{$col}'");
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `vendors` DROP COLUMN `{$col}`");
            }
        }
    }
};
