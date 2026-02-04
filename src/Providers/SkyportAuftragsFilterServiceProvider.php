<?php

namespace SkyportAuftragsFilter\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use SkyportAuftragsFilter\EventProcedures\OrderFilters;

class SkyportAuftragsFilterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var EventProceduresService $eventProceduresService */
        $eventProceduresService = $this->getApplication()->make(EventProceduresService::class);

        for ($i = 1; $i <= 6; $i++) {
            $eventProceduresService->registerFilter(
                'skyport_auftrags_filter_' . $i,
                ProcedureEntry::EVENT_TYPE_ORDER_ITEM,
                [
                    'de' => 'Skyport Auftrags-Filter ' . $i,
                    'en' => 'Skyport Order Filter ' . $i
                ],
                OrderFilters::class . '@filter' . $i
            );
        }
    }
}
