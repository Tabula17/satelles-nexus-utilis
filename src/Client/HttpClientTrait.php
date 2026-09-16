<?php

namespace Tabula17\Satelles\Utilis\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Utilis\Definition\HttpMethodEnum;

trait HttpClientTrait
{

    private function doRequest(
        string           $method,
        string           $url,
        array            $data = [],
        array            $headers = [],
        array            $options = [],
        string           $dataType = 'json', // json, form_params, body, query
        ?LoggerInterface $logger = null
    ): ResponseInterface
    {
        $client = new Client();
        $options = array_merge($options, [
            'headers' => $headers,
            //'json' => $data,
            'http_errors' => false,
        ]);
        if (!empty($data)) {
            $options[$dataType] = $data;
        }
        $logger?->debug('🫯 HTTP CLIENT -> Request: ' . $method . ' ' . $url, $options);

        return $client->request($method, $url, $options);
    }

    /*  public function request(ApiPathConfig $api): ResponseInterface
      {

      }*/

    /**
     * @param HttpMethodEnum $method
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param array $options
     * @param string $dataType
     * @param LoggerInterface|null $logger
     * @return ResponseInterface
     */

    public function requestByMethod(HttpMethodEnum $method, string $url, array $data = [], array $headers = [], array $options = [], string $dataType = 'json', ?LoggerInterface $logger = null): ResponseInterface
    {
        return $this->doRequest($method->value(), $url, $data, $headers, $options, $dataType, $logger);
    }

    /**
     * @throws GuzzleException
     */
    public function post(
        string           $url,
        array            $data = [],
        array            $headers = [],
        array            $options = [],
        string           $dataType = 'json',
        ?LoggerInterface $logger = null
    ): ResponseInterface
    {
        return $this->doRequest('POST', $url, $data, $headers, $options, $dataType, $logger);
    }

    public function get(string $url, array $data = [], array $headers = [], array $options = [], ?LoggerInterface $logger = null): ResponseInterface
    {
        return $this->doRequest(
            method: 'GET',
            url: $url,
            data: $data,
            headers: $headers,
            options: $options,
            dataType: 'query',
            logger: $logger
        );
    }

    public function delete(string $url, array $headers = [], array $options = [], ?LoggerInterface $logger = null): ResponseInterface
    {
        return $this->doRequest(
            method: 'DELETE',
            url: $url,
            headers: $headers,
            options: $options,
            logger: $logger
        );
    }

    public function put(string $url, array $data = [], array $headers = [], array $options = [],
                        string $dataType = 'json', ?LoggerInterface $logger = null): ResponseInterface
    {
        return $this->doRequest('PUT', $url, $data, $headers, $options, $dataType, $logger);
    }

    public function patch(string $url, array $data = [], array $headers = [], array $options = [],
                          string $dataType = 'json', ?LoggerInterface $logger = null): ResponseInterface
    {
        return $this->doRequest('PATCH', $url, $data, $headers, $options, $dataType, $logger);
    }

    public function options(string $url, array $headers = [], array $options = [], ?LoggerInterface $logger = null): ResponseInterface
    {
        return $this->doRequest(
            method: 'OPTIONS',
            url: $url,
            headers: $headers,
            options: $options,
            logger: $logger
        );
    }

    public function head(string $url, array $headers = [], array $options = [], ?LoggerInterface $logger = null): ResponseInterface
    {
        return $this->doRequest(
            method: 'HEAD',
            url: $url,
            headers: $headers,
            options: $options,
            logger: $logger
        );
    }

    public function trace(string $url, array $headers = [], array $options = [], ?LoggerInterface $logger = null): ResponseInterface
    {
        return $this->doRequest(
            method: 'TRACE',
            url: $url,
            headers: $headers,
            options: $options,
            logger: $logger
        );
    }
}