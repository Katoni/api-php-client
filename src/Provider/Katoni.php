<?php

namespace Katoni\OAuth2\Client\Provider;

use Katoni\OAuth2\Client\Provider\Exception\KatoniIdentityProviderException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Katoni extends AbstractProvider
{
    use BearerAuthorizationTrait;

    const BASE_API_URL = 'https://api.katoni.dk/';

    /**
     * @var string
     */
    protected $developerKey;

    /**
     * Get the base API URL.
     *
     * @return string
     */
    private function getBaseAPIUrl()
    {
        return static::BASE_API_URL;
    }

    /**
     * Get authorization url to begin OAuth flow
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->getBaseAPIUrl() . 'oauth2/authorize';
    }

    /**
     * Get access token url to retrieve token
     *
     * @param  array $params
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->getBaseAPIUrl() . 'oauth2/access_token';
    }

    /**
     * Get provider url to fetch user details
     *
     * @param  AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $this->getBaseAPIUrl() . '/v1/account';
    }

    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface!
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return [];
    }

    /**
     * Check a provider response for errors.
     *
     * @link   https://developer.github.com/v3/#client-errors
     * @link   https://developer.github.com/v3/oauth/#common-errors-for-the-access-token-request
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  string $data Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if ($response->getStatusCode() >= 400) {
            throw KatoniIdentityProviderException::clientException($response, $data);
        } elseif (isset($data['error'])) {
            throw KatoniIdentityProviderException::oauthException($response, $data);
        }
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param array $response
     * @param AccessToken $token
     * @return \League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new KatoniResourceOwner($response);
    }
    
    public function setDeveloperKey($developerKey)
    {
        $this->developerKey = $developerKey;
    }

    /**
     * @inheritdoc
     */
    protected function createRequest($method, $url, $token, array $options)
    {
        $defaults = [
            'headers' => $this->getHeaders($token),
        ];

        $url = $this->removeParamsFromUrl($url, ['access_token', 'key']);

        if (is_null($token) && !is_null($this->developerKey)) {
            $url = $this->appendParamsToUrl($url, [
                'key' => $this->developerKey
            ]);
        }

        $options = array_merge_recursive($defaults, $options);
        $factory = $this->getRequestFactory();

        return $factory->getRequestWithOptions($method, $url, $options);
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultHeaders()
    {
        return [
            'User-Agent' => 'katoni'
        ];
    }

    public function get($endpoint, $accessToken = null, $version = null)
    {
        $version = $version ?: 'v1';

        return $this->getResponse($this->getAuthenticatedRequest('GET', $this->getBaseAPIUrl() . $version . $endpoint, $accessToken));
    }

    public function post($endpoint, array $params = [], $accessToken = null, $version = null)
    {
        $version = $version ?: 'v1';

        $options = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => http_build_query($params, null, '&')
        ];

        return $this->getResponse($this->getAuthenticatedRequest('POST', $this->getBaseAPIUrl() . $version . $endpoint, $accessToken, $options));
    }

    /**
     * Remove params from a URL.
     *
     * @param string $url            The URL to filter.
     * @param array  $paramsToFilter The params to filter from the URL.
     *
     * @return string The URL with the params removed.
     */
    protected function removeParamsFromUrl($url, array $paramsToFilter)
    {
        $parts = parse_url($url);

        $query = '';

        if (isset($parts['query'])) {
            $params = [];

            parse_str($parts['query'], $params);

            // Remove query params
            foreach ($paramsToFilter as $paramName) {
                unset($params[$paramName]);
            }

            if (count($params) > 0) {
                $query = '?' . http_build_query($params, null, '&');
            }
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    /**
     * Gracefully appends params to the URL.
     *
     * @param string $url       The URL that will receive the params.
     * @param array  $newParams The params to append to the URL.
     *
     * @return string
     */
    protected function appendParamsToUrl($url, array $newParams = [])
    {
        if (empty($newParams)) {
            return $url;
        }

        if (strpos($url, '?') === false) {
            return $url . '?' . http_build_query($newParams, null, '&');
        }

        list($path, $query) = explode('?', $url, 2);
        $existingParams = [];
        parse_str($query, $existingParams);

        // Favor params from the original URL over $newParams
        $newParams = array_merge($newParams, $existingParams);

        // Sort for a predicable order
        ksort($newParams);

        return $path . '?' . http_build_query($newParams, null, '&');
    }
}