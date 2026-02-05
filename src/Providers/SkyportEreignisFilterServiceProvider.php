<?php

namespace SkyportEreignisFilter\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use SkyportEreignisFilter\EventProcedures\OrderFilters;

class SkyportEreignisFilterServiceProvider extends ServiceProvider
{
    public function boot(EventProceduresService $eventProceduresService): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $eventProceduresService->registerFilter(
                'skyport_auftrags_filter_' . $i,
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
