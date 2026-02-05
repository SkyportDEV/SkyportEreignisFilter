<?php

namespace SkyportEreignisFilter\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Events\BackendMenuEvent;

class BackendServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->getApplication()->registerEvent(
            BackendMenuEvent::class,
            function (BackendMenuEvent $event)
            {
                $event->addEntry([
                    'label' => 'Skyport Ereignis-Filter',
                    'parent' => 'setup',
                    'route'  => 'skyport-ereignis-filter',
                    'icon'   => 'filter'
                ]);
            }
        );
    }
}
