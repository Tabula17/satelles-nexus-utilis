<?php

namespace Tabula17\Satelles\Utilis\Api;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;
use Tabula17\Satelles\Utilis\Config\ApiPathConfig;
use Tabula17\Satelles\Utilis\Definition\HttpStatusEnum;
use Tabula17\Satelles\Utilis\File\MimeTypes;

abstract class ApiResponse
{
    protected(set) MimeTypes $contentType;
    protected(set) HttpStatusEnum $statusCode;
    protected(set) array $headers;
    abstract protected(set) mixed $body {
        set;
    }

    /**
     *
     * @var AbstractDescriptor|null $bodyDescriptor
     */
    protected readonly ?AbstractDescriptor $bodyDescriptor;

    protected array $errors = [];

    /**
     * @param AbstractDescriptor|null $bodyDescriptor
     */
    public function __construct(?AbstractDescriptor $bodyDescriptor = null)
    {
        $this->bodyDescriptor = $bodyDescriptor;
    }

    abstract protected function configure(ApiPathConfig $apiPathConfig): void;

    abstract protected function configureBody(ApiProcessResultCollection $results): void;

    final public function prepare(ApiPathConfig $apiPathConfig, ApiProcessResultCollection $results): self
    {
        try {
            $this->configure($apiPathConfig);
            $this->configureBody($results);
        } catch (\Throwable $err) {
            $this->errors[] = $err->getMessage();
            $this->statusCode = HttpStatusEnum::INTERNAL_SERVER_ERROR;
            $this->contentType = MimeTypes::JSON;
            $this->body = json_encode(['errors' => $this->errors]);
        }
        return $this;
    }

    final public function output()
    {

        http_response_code($this->statusCode->httpCode());
        header('Content-type: ' . $this->contentType->mime());
        foreach ($this->headers as $header => $value) {
            header($header . ': ' . $value);
        }
        return $this->body;
    }

}