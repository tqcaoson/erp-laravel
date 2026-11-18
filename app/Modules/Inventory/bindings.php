<?php

use App\Core\Contracts\StockServiceInterface;
use App\Modules\Inventory\Services\StockService;

return [
    StockServiceInterface::class => StockService::class,
];
