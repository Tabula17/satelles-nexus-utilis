<?php

namespace Tabula17\Satelles\Utilis\Api;


use Tabula17\Satelles\Utilis\Config\ApiPathConfig;
use Tabula17\Satelles\Utilis\Utilities\Request;

/**
 * Handles the execution of an API request by managing processors and results,
 * and generating the corresponding response output.
 */
class ApiRequest
{
    public ApiProcessorsCollection $processors;
    public ApiProcessResultCollection $results;
    private(set) Request $payload;

    /**
     *
     * @param ApiPathConfig $apiPathConfig
     * @param ApiResponse $response
     * @param ApiProcessorsCollection|null $processors
     * @param ApiProcessResultCollection|null $results
     */
    public function __construct(
        public readonly ApiPathConfig $apiPathConfig,
        public readonly ApiResponse $response,
        ?ApiProcessorsCollection $processors = null,
        ?ApiProcessResultCollection $results = null)
    {
        $this->processors = $processors ?? new ApiProcessorsCollection();
        $this->results = $results ?? new ApiProcessResultCollection();
    }

    /**
     * Processes the given API request by applying all processors to the request payload,
     * collecting the results, and preparing the response.
     *
     * @return ApiResponse Processed API response
     */
    public function process(): ApiResponse
    {
        $this->payload = new Request();
        $this->processors->each(function(ApiProcessInterface $processor){
            $this->results->add($processor->process($this->apiPathConfig, $this->payload));
        });
        return $this->response->prepare($this->apiPathConfig, $this->results);
    }
}