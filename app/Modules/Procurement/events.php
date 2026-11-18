<?php

use App\Modules\Inventory\Events\StockBelowMinimum;
use App\Modules\Procurement\Listeners\SuggestPurchaseRequest;

return [
    StockBelowMinimum::class => [SuggestPurchaseRequest::class],
];
