namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Config\Services;

class AuthApiFilter implements FilterInterface
{
    protected $rateLimit = 100; // max requests
    protected $window = 900;    // 15 minutes (in seconds)

    public function before(RequestInterface $request, $arguments = null)
    {
        // 1. Rate limiting by IP
        $ip = $_SERVER['REMOTE_ADDR'];
        $cache = cache();
        $key = 'rate_limit_' . md5($ip);
        $entry = $cache->get($key);

        if (!$entry) {
            $entry = ['count' => 1, 'start' => time()];
        } else {
            $entry['count']++;
        }

        // if window expired, reset
        if (time() - $entry['start'] > $this->window) {
            $entry = ['count' => 1, 'start' => time()];
        }

        if ($entry['count'] > $this->rateLimit) {
            return Services::response()
                ->setStatusCode(429)
                ->setJSON(['error' => 'Too Many Requests']);
        }

        $cache->save($key, $entry, $this->window);

        // 2. Check Content-Type (only JSON accepted)
        $method = $request->getMethod();
        $contentType = $request->getHeaderLine('Content-Type');

        if (in_array($method, ['POST', 'PUT', 'PATCH']) && stripos($contentType, 'application/json') === false) {
            return Services::response()
                ->setStatusCode(415)
                ->setJSON(['error' => 'Unsupported Media Type. Use application/json']);
        }

        // 3. Check JWT Authorization Header
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON(['error' => 'Missing Authorization Header']);
        }

        $token = explode(' ', $authHeader)[1];

        try {
            $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET'), 'HS256'));
            $request->user = $decoded;
        } catch (\Exception $e) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON(['error' => 'Invalid or expired token']);
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
