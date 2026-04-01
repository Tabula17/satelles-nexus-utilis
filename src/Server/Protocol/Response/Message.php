<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class Message extends Base
{
    protected(set) string $channel;
    protected(set) array|AbstractDescriptor $data = [] {
        set(array|AbstractDescriptor|string $data) {
            if(is_string($data)){
                $data = json_validate($data) ? json_decode($data, true) : ['message' => $data, 'type' => 'text'];
            }
            $this->data = $data instanceof AbstractDescriptor ? $data->toArray() : $data;
        }
    }

    public function __construct(
        ?array                $values = [],
        private readonly Type $responseTypes = new Type()
    )
    {
        if (empty($values)) {
            $values = [];
        }
        $values['type'] = $responseTypes->message;
        parent::__construct($values, $responseTypes);
        $this->addValidator(fn() => $this->channel && $this->data && $this->type === $this->responseTypes->message);
    }
}