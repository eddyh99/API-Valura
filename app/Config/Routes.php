<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

//  Login JWT Token
$routes->post('login', 'Auth::postLogin');

// Refresh JWT Token
$routes->post('refresh-token', 'Auth::refreshToken');

// Data Pengguna (insert update delete)
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('user', 'V1\User::create');
    $routes->put('user/(:num)', 'V1\User::update/$1');
    $routes->delete('user/(:num)', 'V1\User::delete/$1');

    $routes->get('user', 'V1\User::show_all_users');
    $routes->get('user/(:num)', 'V1\User::showUser_ByID/$1');
});

// Role Pengguna (insert update delete)
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('role', 'V1\Role::create');
    $routes->put('role/(:num)', 'V1\Role::update/$1');
    $routes->delete('role/(:num)', 'V1\Role::delete/$1');

    $routes->get('role', 'V1\Role::show_all_roles');
    $routes->get('role/(:num)', 'V1\Role::showRole_ByID/$1');
});

// Add Currency (insert update delete)
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('currency', 'V1\Currency::create');
    $routes->put('currency/(:num)', 'V1\Currency::update/$1');
    $routes->delete('currency/(:num)', 'V1\Currency::delete/$1');

    $routes->get('currency', 'V1\Currency::show_all_currencies');
    $routes->get('currency/(:num)', 'V1\Currency::showCurrency_ByID/$1');
});

// Exchange Rate (insert update delete)
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('exchange-rate', 'V1\ExchangeRate::create');
    $routes->put('exchange-rate/(:num)', 'V1\ExchangeRate::update/$1');
    $routes->delete('exchange-rate/(:num)', 'V1\ExchangeRate::delete/$1');

    $routes->get('exchange-rate', 'V1\ExchangeRate::show_all_exchangeRates');
    $routes->get('exchange-rate/(:num)', 'V1\ExchangeRate::showExchangeRate_ByID/$1');
});

