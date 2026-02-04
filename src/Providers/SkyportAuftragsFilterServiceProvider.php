<?php

namespace SkyportAuftragsFilter\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use SkyportAuftragsFilter\EventProcedures\OrderFilters;

class SkyportAuftragsFilterServiceProvider extends ServiceProvider
{
    public function boot(EventProceduresService $eventProceduresService): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $eventProceduresService->registerFilter(
                'skyportOrderFilter' . $i,
                ProcedureEntry::EVENT_TYPE_ORDER,
                [
                    'de' => 'Skyport Auftrags-Filter ' . $i,
                    'en' => 'Skyport Order Filter ' . $i
                ],
                OrderFilters::class . '@filter' . $i
            );
        }
    }
}
