<?php
// Modules/Inventory/Jobs/CheckExpiredBatches.php

namespace Modules\Inventory\Jobs;

use Modules\Inventory\Models\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckExpiredBatches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ingredientId;

    public function __construct(int $ingredientId)
    {
        $this->ingredientId = $ingredientId;
    }

    public function handle()
    {
        $expired = Batch::where('ingredient_id', $this->ingredientId)
            ->where('status', 'active')
            ->where('expiry_date', '<=', now())
            ->get();

        foreach ($expired as $batch) {
            $batch->update(['status' => 'expired']);
            Log::warning("Batch {$batch->batch_number} expired", ['id' => $batch->id]);
        }

        Cache::tags(['batches', 'stock'])->flush();
    }
}
