<?php

namespace SkyportEreignisFilter\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

class RouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router)
    {
        $router->get(
            'skyport-ereignis-filter',
            'SkyportEreignisFilter\Controllers\BackendController@view'
        );
    }
}
