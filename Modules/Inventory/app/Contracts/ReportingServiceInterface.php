<?php

namespace Modules\Inventory\Contracts;

use Illuminate\Support\Collection;

interface ReportingServiceInterface
{
    public function getLowStockIngredients(int $limit = 10): Collection;
    public function getInventoryValues(): array;
    public function getStockMovementReport(int $days = 30): Collection;
    public function getAbcAnalysis(): Collection;
}
