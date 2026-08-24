<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('v1', static function (RouteCollection $routes) {
    $routes->get('/', 'Home::index');

    $routes->get('devs', 'DevController::index');
    $routes->get('devs/(:segment)', 'DevController::show/$1');

    $routes->get('channels', 'ChannelController::index');
    // Regex bruta, não (:any): searchQuery pode ser um link inteiro com barras
    // (ex. https://youtube.com/canal) — (:any) não cruza "/" por padrão no CI4 4.7
    // (opt-in via Config\Routing::$multipleSegmentsOneParam, não usado aqui de propósito
    // pra não mudar o comportamento de todas as outras rotas).
    $routes->get('channels/(.*)', 'ChannelController::show/$1');

    $routes->get('feed/trending', 'VideoController::trending');
    $routes->get('feed/channel', 'VideoController::byChannel');
    $routes->get('video/(:segment)', 'VideoController::show/$1');

    $routes->get('description/feed', 'DescriptionController::feed');
    $routes->get('description/category', 'DescriptionController::category');
});
