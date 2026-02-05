<?php

namespace SkyportEreignisFilter\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use SkyportEreignisFilter\EventProcedures\OrderFilters;
use SkyportEreignisFilter\Providers\SkyportEreignisFilterRouteServiceProvider;
use SkyportEreignisFilter\Providers\SkyportEreignisFilterBackendServiceProvider;

class SkyportEreignisFilterServiceProvider extends ServiceProvider
{
    public function boot(EventProceduresService $eventProceduresService): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $eventProceduresService->registerFilter(
                'skyport_ereignis_filter_' . $i,
                ProcedureEntry::EVENT_TYPE_ORDER,
                [
                    'de' => 'Skyport Ereignis-Filter ' . $i,
                    'en' => 'Skyport Event Filter ' . $i
                ],
                OrderFilters::class . '@filter' . $i
            );
        }
    }

    public function register()
    {
        $this->getApplication()->register(SkyportEreignisFilterRouteServiceProvider::class);
        $this->getApplication()->register(SkyportEreignisFilterBackendServiceProvider::class);
    }
}
