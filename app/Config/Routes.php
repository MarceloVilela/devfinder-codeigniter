<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('v1', static function (RouteCollection $routes) {
    $routes->get('/', 'Home::index');

    $routes->get('devs', 'DevController::index', ['filter' => 'optionalAuth']);
    $routes->post('devs', 'DevController::store', ['filter' => 'requiredAuth']);
    $routes->get('devs/(:segment)', 'DevController::show/$1');

    $routes->get('channels', 'ChannelController::index');
    $routes->post('channels', 'ChannelController::store', ['filter' => 'requiredAuth']);
    // Regex bruta, não (:any): searchQuery pode ser um link inteiro com barras
    // (ex. https://youtube.com/canal) — (:any) não cruza "/" por padrão no CI4 4.7
    // (opt-in via Config\Routing::$multipleSegmentsOneParam, não usado aqui de propósito
    // pra não mudar o comportamento de todas as outras rotas).
    $routes->get('channels/(.*)', 'ChannelController::show/$1');

    $routes->get('feed/trending', 'VideoController::trending', ['filter' => 'optionalAuth']);
    $routes->get('feed/channel', 'VideoController::byChannel');
    $routes->post('video', 'VideoController::store', ['filter' => 'requiredAuth']);
    $routes->post('video/refresh', 'VideoController::refresh', ['filter' => 'requiredAuth']);
    $routes->get('video/(:segment)', 'VideoController::show/$1');

    $routes->get('description/feed', 'DescriptionController::feed');
    $routes->get('description/category', 'DescriptionController::category');

    $routes->get('auth/github', 'AuthController::github');
    $routes->get('auth/github/callback', 'AuthController::callback');

    $routes->get('me', 'MeController::show', ['filter' => 'requiredAuth']);

    $routes->get('likes/devs', 'DevReactionController::likedDevs', ['filter' => 'requiredAuth']);
    $routes->get('dislikes/devs', 'DevReactionController::dislikedDevs', ['filter' => 'requiredAuth']);
    $routes->post('likes/devs/(:segment)', 'DevReactionController::likeStore/$1', ['filter' => 'requiredAuth']);
    $routes->delete('likes/devs/(:segment)', 'DevReactionController::likeDelete/$1', ['filter' => 'requiredAuth']);
    $routes->post('dislikes/devs/(:segment)', 'DevReactionController::dislikeStore/$1', ['filter' => 'requiredAuth']);
    $routes->delete('dislikes/devs/(:segment)', 'DevReactionController::dislikeDelete/$1', ['filter' => 'requiredAuth']);

    $routes->post('likes/channels/(:segment)', 'ChannelReactionController::followStore/$1', ['filter' => 'requiredAuth']);
    $routes->delete('likes/channels/(:segment)', 'ChannelReactionController::followDelete/$1', ['filter' => 'requiredAuth']);
    $routes->post('dislikes/channels/(:segment)', 'ChannelReactionController::ignoreStore/$1', ['filter' => 'requiredAuth']);
    $routes->delete('dislikes/channels/(:segment)', 'ChannelReactionController::ignoreDelete/$1', ['filter' => 'requiredAuth']);
});
