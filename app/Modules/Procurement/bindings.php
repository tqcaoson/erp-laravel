<?php

use App\Core\Contracts\PurchaseRequestCreatorInterface;
use App\Modules\Procurement\Services\PurchaseRequestService;

return [
    PurchaseRequestCreatorInterface::class => PurchaseRequestService::class,
];
