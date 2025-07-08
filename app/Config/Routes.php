<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

//  Login JWT Token
$routes->post('login', 'Auth::postLogin');

// Refresh JWT Token
$routes->post('refresh-token', 'Auth::refreshToken');

$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('user', 'V1\User::create');
});

