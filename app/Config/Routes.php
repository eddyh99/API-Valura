<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

//  Log in JWT Token
$routes->post('login', 'Auth::postLogin');

// Refresh JWT Token
$routes->post('refresh-token', 'Auth::refreshToken');

// Forgot Password
$routes->post('forgot-password-otp', 'Auth::postForgotPasswordOtp');
$routes->post('reset-password-otp', 'Auth::postResetPasswordOtp');

// Signup
$routes->post('register', 'Auth::postRegister');
$routes->post('activate-otp', 'Auth::postActivateOtp');
$routes->post('resend-otp', 'Auth::postResendOtp');

// Log out
$routes->post('logout', 'Auth::postLogout');

// Data Pengguna
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('user', 'V1\User::create');
    $routes->put('user/(:num)', 'V1\User::update/$1');
    $routes->delete('user/(:num)', 'V1\User::delete/$1');

    $routes->get('user', 'V1\User::show_all_users');
    $routes->get('user/(:num)', 'V1\User::showUser_ByID/$1');
});

// Role Pengguna
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('role', 'V1\Role::create');
    $routes->put('role/(:num)', 'V1\Role::update/$1');
    $routes->delete('role/(:num)', 'V1\Role::delete/$1');

    $routes->get('role', 'V1\Role::show_all_roles');
    $routes->get('role/(:num)', 'V1\Role::showRole_ByID/$1');
});

// Currency
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('currency', 'V1\Currency::create');
    $routes->put('currency/(:num)', 'V1\Currency::update/$1');
    $routes->delete('currency/(:num)', 'V1\Currency::delete/$1');

    $routes->get('currency', 'V1\Currency::show_all_currencies');
    $routes->get('currency/(:num)', 'V1\Currency::showCurrency_ByID/$1');
    $routes->get('currency/default', 'V1\Currency::show_default_currencies');

    $routes->post('currency/recap', 'V1\Currency::rekapCurrencyPenukaran');
});

// Exchange Rate
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('exchange-rate', 'V1\ExchangeRate::create');
    $routes->put('exchange-rate/(:num)', 'V1\ExchangeRate::update/$1');
    $routes->delete('exchange-rate/(:num)', 'V1\ExchangeRate::delete/$1');

    $routes->get('exchange-rate', 'V1\ExchangeRate::show_all_exchangeRates');
    $routes->get('exchange-rate/(:num)', 'V1\ExchangeRate::showExchangeRate_ByID/$1');
});

// Branch
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('branch', 'V1\Branch::create');
    $routes->put('branch/(:num)', 'V1\Branch::update/$1');
    $routes->delete('branch/(:num)', 'V1\Branch::delete/$1');

    $routes->get('branch', 'V1\Branch::show_all_branches');
    $routes->get('branch/(:num)', 'V1\Branch::showBranch_ByID/$1');
});

// Cash
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('cash', 'V1\Cash::create');
    $routes->put('cash/(:num)', 'V1\Cash::update/$1');
    $routes->delete('cash/(:num)', 'V1\Cash::delete/$1');

    $routes->get('cash', 'V1\Cash::show_all_cashes');
    $routes->get('cash/(:num)', 'V1\Cash::showCash_ByID/$1');
    $routes->get('cash/branch/(:num)', 'V1\Cash::showCash_ByBranchID/$1');

    $routes->get('report/daily-cash', 'V1\Cash::showDailyCash');
    $routes->get('cash/recap', 'V1\Cash::showRecapCash');
});

// Transaction
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->get('transaction/(:num)', 'V1\Transaction::showTransaction_ByID/$1');
    $routes->post('transaction', 'V1\Transaction::create');
    $routes->put('transaction/(:num)', 'V1\Transaction::update/$1');
    $routes->delete('transaction/(:num)', 'V1\Transaction::delete/$1');
    $routes->get('transaction/daily', 'V1\Transaction::showDailyTransaction');

    $routes->get('report/client', 'V1\Transaction::showClientRecap');
});

// Bank
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('bank', 'V1\Bank::create');
    $routes->put('bank/(:num)', 'V1\Bank::update/$1');
    $routes->delete('bank/(:num)', 'V1\Bank::delete/$1');

    $routes->get('bank', 'V1\Bank::show_all_banks');
    $routes->get('bank/(:num)', 'V1\Bank::showBank_ByID/$1');

    $routes->post('bank-settlement', 'V1\Bank::create_settlement');
});

// Agent
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('agent', 'V1\Agent::create');
    $routes->put('agent/(:num)', 'V1\Agent::update/$1');
    $routes->delete('agent/(:num)', 'V1\Agent::delete/$1');

    $routes->get('agent', 'V1\Agent::show_all_agents');
    $routes->get('agent/(:num)', 'V1\Agent::showAgent_ByID/$1');
});

// Client
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('client', 'V1\Client::create');
    $routes->put('client/(:num)', 'V1\Client::update/$1');
    $routes->delete('client/(:num)', 'V1\Client::delete/$1');

    $routes->get('client', 'V1\Client::show_all_clients');
    $routes->get('client/(:num)', 'V1\Client::showClient_ByID/$1');

    
});

// Bank Deposit (Penukaran)
$routes->group('', ['filter' => 'authApi'], function($routes) {
    $routes->post('bank-deposit', 'V1\BankDeposit::create');
});

// Report
