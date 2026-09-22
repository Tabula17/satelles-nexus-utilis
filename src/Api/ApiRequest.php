<?php

namespace Tabula17\Satelles\Utilis\Api;


use Psr\Log\LoggerInterface;
use Tabula17\Satelles\Utilis\Config\ApiPathConfig;
use Tabula17\Satelles\Utilis\Definition\HttpStatusEnum;
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
    private string $runtimeError = '';

    /**
     *
     * @param ApiPathConfig $apiPathConfig
     * @param ApiResponse $response
     * @param ApiProcessorsCollection|null $processors
     * @param ApiProcessResultCollection|null $results
     */
    public function __construct(
        public readonly ApiPathConfig    $apiPathConfig,
        public readonly ApiResponse      $response,
        ?ApiProcessorsCollection         $processors = null,
        ?ApiProcessResultCollection      $results = null,
        public readonly ?LoggerInterface $logger = null
    )
    {
        $this->processors = $processors ?? new ApiProcessorsCollection();
        $this->results = $results ?? new ApiProcessResultCollection();
        $this->payload = new Request();
        $this->apiPathConfig->options->setValues($this->payload->params);
        $this->logger?->debug('API Request started', $this->payload->params);
    }

    /**
     * Processes the given API request by applying all processors to the request payload,
     * collecting the results, and preparing the response.
     *
     * @return ApiResponse Processed API response
     */
    public function process(): ApiResponse
    {
        if (PHP_SAPI !== 'cli' && $this->apiPathConfig->method !== $this->payload->method) {
            $this->logger?->warning('Method not allowed', ['method' => $this->payload->method, 'path' => $this->apiPathConfig->path]);
            return $this->response->prepare($this->apiPathConfig, $this->results, HttpStatusEnum::METHOD_NOT_ALLOWED, [
                HttpStatusEnum::METHOD_NOT_ALLOWED->message()
            ]);
        }
        $this->processors->until(function (ApiProcessInterface $processor) {
            try {
                $result = $processor->process($this->apiPathConfig, $this->payload);
                $this->results->add($result);
                return !$result->halt();
            } catch (\Throwable $exception) {
                $this->runtimeError = $exception->getMessage();
                $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
                $this->logger?->debug('Loop halted -> ' . $exception->getTraceAsString());
                return false;
            }
        });
        if (!empty($this->runtimeError)) {
            $this->logger?->debug('Response halted by error: ' . $this->runtimeError);
            return $this->response->prepare($this->apiPathConfig, $this->results, HttpStatusEnum::BAD_REQUEST, [$this->runtimeError]);
        }
        return $this->response->prepare($this->apiPathConfig, $this->results);
    }
}