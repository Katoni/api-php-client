<?php
namespace Katoni;

use InvalidArgumentException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use Katoni\Auth\Middleware\Simple;
use Katoni\Http\Request;
use Psr\Http\Message\RequestInterface;

class Katoni
{
    const VERSION = '1.0';
    const USER_AGENT_SUFFIX = "katoni-api-php-client/";
    const API_BASE_PATH = 'https://api.katoni.dk';

    /**
     * @var array
     */
    private $config;

    /**
     * @var array access token
     */
    private $token;

    /**
     * @var \GuzzleHttp\ClientInterface $http
     */
    private $http;

    public function __construct($config = [])
    {
        $this->config = array_merge([
            'application_name' => '',

            // Don't change these unless you're working against a special development
            // or testing environment.
            'base_path' => self::API_BASE_PATH,

            // https://developers.katoni.dk/console
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => null,
            'state' => null,

            // Simple API access key, also from the API console. Ensure you get
            // a Server key, and not a Browser key.
            'developer_key' => '',
        ], $config);
    }

    /**
     * Get a string containing the version of the library.
     *
     * @return string
     */
    public function getVersion()
    {
        return self::VERSION;
    }

    /**
     * Set the Http Client object
     * @param \GuzzleHttp\ClientInterface $http
     */
    public function setHttpClient(ClientInterface $http)
    {
        $this->http = $http;
    }

    /**
     * @return \GuzzleHttp\ClientInterface implementation
     */
    public function getHttpClient()
    {
        if (is_null($this->http)) {
            $this->http = $this->createDefaultHttpClient();
        }

        return $this->http;
    }

    protected function createDefaultHttpClient()
    {
        $options = [
            'exceptions' => false,
            'base_uri' => $this->config['base_path']
        ];

        return new Client($options);
    }

    /**
     * Set the developer key to use, these are obtained through the API Console.
     * @param string $developerKey
     */
    public function setDeveloperKey($developerKey)
    {
        $this->config['developer_key'] = $developerKey;
    }

    /**
     * Set the application name, this is included in the User-Agent HTTP header.
     * @param string $applicationName
     */
    public function setApplicationName($applicationName)
    {
        $this->config['application_name'] = $applicationName;
    }

    /**
     * @param string|array $token
     * @throws \InvalidArgumentException
     */
    public function setAccessToken($token)
    {
        if (is_string($token)) {
            if ($json = json_decode($token, true)) {
                $token = $json;
            } else {
                // assume $token is just the token string
                $token = array(
                    'access_token' => $token,
                );
            }
        }
        if ($token == null) {
            throw new InvalidArgumentException('invalid json token');
        }
        if (!isset($token['access_token'])) {
            throw new InvalidArgumentException("Invalid token format");
        }
        $this->token = $token;
    }

    public function getAccessToken()
    {
        return $this->token;
    }


    public function getRefreshToken()
    {
        return isset($this->token['refresh_token']) ? $this->token['refresh_token'] : null;
    }

    /**
     * Returns if the access_token is expired.
     * @return bool Returns True if the access_token is expired.
     */
    public function isAccessTokenExpired()
    {
        if (!$this->token) {
            return true;
        }

        $created = 0;

        if (isset($this->token['created'])) {
            $created = $this->token['created'];
        } elseif (isset($this->token['id_token'])) {
            // check the ID token for "iat"
            // signature verification is not required here, as we are just
            // using this for convenience to save a round trip request
            // to the Google API server
            $idToken = $this->token['id_token'];
            if (substr_count($idToken, '.') == 2) {
                $parts = explode('.', $idToken);
                $payload = json_decode(base64_decode($parts[1]), true);
                if ($payload && isset($payload['iat'])) {
                    $created = $payload['iat'];
                }
            }
        }

        // If the token is set to expire in the next 30 seconds.
        $expired = ($created + ($this->token['expires_in'] - 30)) < time();

        return $expired;
    }




    public function get($endpoint)
    {
        return $this->request('GET', $endpoint);
    }

    public function post($endpoint, array $params = [])
    {
        return $this->request('POST', $endpoint, $params);
    }

    public function request($method, $endpoint, array $params = [])
    {
        $request = new Request($method, $endpoint, $params);

        return $this->execute($request);




        /*$request = $this->guzzleClient->createRequest($method, $url, $options);
        try {
            $rawResponse = $this->guzzleClient->send($request);
        } catch (RequestException $e) {
            $rawResponse = $e->getResponse();
            if ($e->getPrevious() instanceof RingException || !$rawResponse instanceof ResponseInterface) {
                throw new FacebookSDKException($e->getMessage(), $e->getCode());
            }
        }
        $rawHeaders = $this->getHeadersAsString($rawResponse);
        $rawBody = $rawResponse->getBody();
        $httpStatusCode = $rawResponse->getStatusCode();
        return new GraphRawResponse($rawHeaders, $rawBody, $httpStatusCode);

        return $this->http->request($method, $endpoint);*/
    }

    /**
     * @param \Katoni\Request $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function execute(Request $request)
    {
        $request = $request->withHeader('User-Agent', $this->config['application_name'] . " " . self::USER_AGENT_SUFFIX . $this->getVersion());

        $http = $this->authorize();

        $options = [
            'verify' => true
        ];

        $response = $http->request('GET', 'https://api.katoni.dk/products', $options);

        var_dump($response->getBody()->getContents());

        return;

        list($url, $method, $headers, $body) = $this->prepareRequestMessage($request);

        $options = [
            'headers' => $headers,
            'body' => $body,
            'timeout' => $request->containsFileUploads() ? 3600 : 60,
            'connect_timeout' => 10,
            'verify' => true,//__DIR__ . '/../../test.pem',
        ];

        $response = $this->http->request($method, $url, $options);

        return $response;
    }
    
    private function prepareRequestMessage(Request $request)
    {
        $body = '';
        
        return [
            $request->getEndpoint(),
            $request->getMethod(),
            $request->getHeaders(),
            $body
        ];
    }

    /**
     * Helper method to execute deferred HTTP requests.
     *
     * @param $request \Psr\Http\Message\RequestInterface|Google_Http_Batch
     * @throws Google_Exception
     * @return object of the type of the expected class or \Psr\Http\Message\ResponseInterface.
     */
    /*public function execute(RequestInterface $request, $expectedClass = null)
    {
        $request = $request->withHeader(
            'User-Agent',
            $this->config['application_name']
            . " " . self::USER_AGENT_SUFFIX
            . $this->getVersion()
        );

        // call the authorize method
        // this is where most of the grunt work is done
        $http = $this->authorize();

        return Google_Http_REST::execute($http, $request, $expectedClass, $this->config['retry']);
    }*/

    /**
     * Adds auth listeners to the HTTP client based on the credentials
     * set in the Google API Client object
     *
     * @param \GuzzleHttp\ClientInterface $http the http client object.
     * @param \GuzzleHttp\ClientInterface $authHttp an http client for authentication.
     * @return \GuzzleHttp\ClientInterface the http client object
     */
    public function authorize(ClientInterface $http = null, ClientInterface $authHttp = null)
    {
        $credentials = null;
        $token = null;
        $scopes = null;

        if (is_null($http)) {
            $http = $this->getHttpClient();
        }

        // These conditionals represent the decision tree for authentication
        //   1.  Check for Application Default Credentials
        //   2.  Check for API Key
        //   3a. Check for an Access Token
        //   3b. If access token exists but is expired, try to refresh it
        /*if ($this->isUsingApplicationDefaultCredentials()) {
            $credentials = $this->createApplicationDefaultCredentials();
        } elseif ($token = $this->getAccessToken()) {
            $scopes = $this->prepareScopes();
            // add refresh subscriber to request a new token
            if ($this->isAccessTokenExpired() && isset($token['refresh_token'])) {
                $credentials = $this->createUserRefreshCredentials(
                    $scopes,
                    $token['refresh_token']
                );
            }
        }
        $authHandler = $this->getAuthHandler();
        if ($credentials) {
            $callback = $this->config['token_callback'];
            $http = $authHandler->attachCredentials($http, $credentials, $callback);
        } elseif ($token) {
            $http = $authHandler->attachToken($http, $token, (array) $scopes);
        } elseif ($key = $this->config['developer_key']) {
            $http = $authHandler->attachKey($http, $key);
        }*/

        $middleware = new Simple(['key' => $this->config['developer_key']]);
        $config = $this->http->getConfig();
        $config['handler']->remove('katoni_auth');
        $config['handler']->push($middleware, 'katoni_auth');
        $config['auth'] = 'simple';
        $http = new Client($config);

        return $http;
    }
}

/*if (version_compare(PHP_VERSION, '5.4.0', '<')) {
    throw new Exception('The Facebook SDK requires PHP version 5.4 or higher.');
}*/