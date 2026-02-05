<?php

namespace SkyportEreignisFilter\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;

class BackendController extends Controller
{
    public function view(Twig $twig)
    {
        return $twig->render('SkyportEreignisFilter::index');
    }
}
