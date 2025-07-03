<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->post('login', 'Auth::postLogin');
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('user', 'V1\User::create');
});

