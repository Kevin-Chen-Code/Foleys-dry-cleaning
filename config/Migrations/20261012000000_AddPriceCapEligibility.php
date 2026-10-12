<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class AddPriceCapEligibility extends BaseMigration
{
    public function up(): void
    {
        $this->table('service_items')
            ->addColumn('price_cap_eligible', 'boolean', ['default' => true])
            ->update();
        // Persist exclusions so renaming an existing garment does not re-enable deals.
        $this->execute("UPDATE service_items SET price_cap_eligible = FALSE WHERE LOWER(TRIM(name)) IN ('bar robe', 'bar robes', 'jabot', 'jabots')");
    }

    public function down(): void
    {
        $this->table('service_items')->removeColumn('price_cap_eligible')->update();
    }
}
