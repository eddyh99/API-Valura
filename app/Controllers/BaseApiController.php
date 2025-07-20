<?php
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class BaseApiController extends ResourceController
{
    protected $validation;
    protected $emailService;
    protected $request;


    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Always call parent
        parent::initController($request, $response, $logger);

        date_default_timezone_set('Asia/Singapore');

        // Define shared services
        $this->validation   = service('validation');
        $this->emailService = service('email');
    }
}
