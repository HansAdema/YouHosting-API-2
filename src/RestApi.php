<?php

namespace YouHosting;
use GuzzleHttp\Message\ResponseInterface;

/**
 * Class RestApi
 *
 * Improve many methods using the official Rest API where available
 *
 * @package YouHosting
 */
class RestApi extends WebApi
{
    protected $apiKey;
    protected $apiClient;
    protected $apiOptions = array(
        'api_url' => 'https://rest.main-hosting.com',
        'verify_ssl' => false,
    );

    /**
     * Setup a new RestApi
     *
     * @param string $username
     * @param string $password
     * @param array $options
     * @param string $apiKey
     */
    public function __construct($username, $password, $options, $apiKey)
    {
        parent::__construct($username, $password, array_merge($this->apiOptions, $options));
        $this->apiKey = $apiKey;

        $this->apiClient = new \GuzzleHttp\Client(array(
            'base_url' => $this->options['api_url'],
            'defaults' => array(
                'verify' => $this->options['verify_ssl'],
                'connect_timeout' => 20,
                'timeout' => 30,
                'auth' => array('reseller', $this->apiKey),
            ),
        ));
    }

    /**
     * Get a client from YouHosting
     *
     * @param $id
     * @return Client
     */
    public function getClient($id)
    {
        return new Client($this->apiGet("/v1/client/" . $id));
    }

    /**
     * Perform a GET request
     *
     * @param string $url
     * @param array $data
     * @return mixed
     */
    protected function apiGet($url, $data = array())
    {
        if (!empty($data)) {
            $url = $url . "?" . http_build_query($data);
        }

        $response = $this->apiClient->get($url);

        return $this->processResponse($response);
    }

    /**
     * Preprocess the result to check whether the request was successful
     *
     * @param ResponseInterface $response
     * @return mixed
     * @throws YouHostingException
     */
    private function processResponse(ResponseInterface $response)
    {
        if($response->getStatusCode() != 200){
            throw new YouHostingException("The API returned with a non-successful error code: ".$response->getStatusCode());
        }

        $json = $response->json();

        if(!empty($json['error'])){
            throw new YouHostingException($json['error']['message'], $json['error']['code']);
        }

        return $json['result'];
    }

    public function listClients($page)
    {
        $result = $this->apiGet("/v1/client/list", array(
            'page' => $page,
            'per_page' => 100,
        ));

        array_walk($result['list'], function ($value, $key) {
            $result['list'][$key] = new Client($value);
        });

        return $result;
    }

    public function getClientLoginUrl($id)
    {
        return $this->apiGet("/v1/client/" . $id . "/login-url");
    }

    public function getAccountLoginUrl($id)
    {
        return $this->apiGet("/v1/account/" . $id . "/login-url");
    }

    public function getAccount($id)
    {
        return new Account($this->apiGet("/v1/account/" . $id));
    }

    public function checkDomain($type, $domain, $subdomain)
    {
        return $this->apiGet('/v1/account/check', array(
            'type' => $type,
            'domain' => $domain,
            'subdomain' => $subdomain,
        ));
    }

    public function listAccounts($page)
    {
        $result = $this->apiGet("/v1/account/list", array(
            'page' => $page,
            'per_page' => 100,
        ));

        array_walk($result['list'], function ($value, $key) {
            $result['list'][$key] = new Account($value);
        });

        return $result;
    }

    public function getClientAccounts($id)
    {
        $result = $this->apiGet("/v1/account/list", array(
            'page' => 1,
            'per_page' => 100,
            'client_id' => $id
        ));

        return array_map(function ($value) {
            return new Account($value);
        }, $result['list']);
    }

    public function searchClientId($email)
    {
        $result = $this->apiGet("/v1/client/list", array(
            'page' => 1,
            'per_page' => 100,
            'email' => $email
        ));

        return array_shift($result['list'])['id'];
    }

    public function suspendAccount($id, $reason, $info)
    {
        return $this->apiPost("/v1/account/" . $id . "/suspend", array(
            'id' => $id
        ));
    }

    /**
     * Perform a POST request
     *
     * @param string $url
     * @param array $data
     * @return mixed
     */
    protected function apiPost($url, $data)
    {
        if (empty($data['client_ip'])) {
            $data['client_ip'] = sprintf("%d.%d.%d.%d", rand(1, 244), rand(1, 244), rand(1, 244), rand(1, 244));
        }

        $response = $this->apiClient->post($url, array(
            'body' => $data,
        ));

        return $this->processResponse($response);
    }

    public function unsuspendAccount($id, $reason, $info)
    {
        return $this->apiPost("/v1/account/".$id."/unsuspend", array(
            'id' => $id
        ));
    }

    public function getSubdomains()
    {
        return $this->apiGet('/v1/settings/subdomains');
    }

    public function getPlans()
    {
        $plans = $this->apiGet('/v1/settings/plans');

        $return = array();
        foreach($plans as $plan){
            $return[] = new HostingPlan($plan);
        }

        return $return;
    }

    public function getNameservers()
    {
        return $this->apiGet('/v1/settings/nameservers');
    }

}