<?php

namespace Modules\Inventory\Jobs;

use Modules\Inventory\Services\ReportingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class UpdateStockAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ingredientId;

    public function __construct(int $ingredientId)
    {
        $this->ingredientId = $ingredientId;
    }

    public function handle(ReportingService $reporting)
    {
        Cache::tags(['analytics', 'reports'])->flush();
        $reporting->getInventoryValue();
        $reporting->getLowStockIngredients();
    }
}
