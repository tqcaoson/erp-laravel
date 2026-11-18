<?php

use App\Modules\Accounting\Events\InvoicePosted;
use App\Modules\Reporting\Listeners\ClearReportCacheOnInvoicePosted;

return [
    InvoicePosted::class => [ClearReportCacheOnInvoicePosted::class],
];
