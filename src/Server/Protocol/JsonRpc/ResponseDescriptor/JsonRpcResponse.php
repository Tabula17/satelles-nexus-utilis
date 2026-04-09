<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\JsonRpc\ResponseDescriptor;

use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Type\ErrorDescriptor;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

//<-- {"jsonrpc": "2.0", "error": {"code": -32600, "message": "Invalid Request"}, "id": null}
class JsonRpcResponse extends AbstractDescriptor
{
    protected(set) int|string|null $id;
    protected(set) string $jsonrpc = '2.0';
    protected(set) mixed $result;
    /**
     * Error Code           Message             Description
     * −32700               Parse error         Invalid JSON was received
     * −32600               Invalid Request     The JSON sent is not a valid Request object
     * −32601               Method not found    The method does not exist / is not available
     * −32602               Invalid params      Invalid method parameter(s)
     * −32603               Internal error      Internal JSON-RPC error
     * −32000 to −32099     Server error        Reserved for implementation-defined server errors
     */
    protected(set) ?ErrorDescriptor $error {
        set(ErrorDescriptor|array|null $error) {
            if($error) {
                if (is_array($error)) {
                    $error = new ErrorDescriptor($error);
                }
                if ($error->code > 0) {
                    $error->set('code', $error->code * -1);
                }

                $code = abs($error->code);
                if (($code < 32000 || $code > 32099) && !static::errorCodeExists($code)) {
                    $error->set('code', -32000);
                    $error->set('message', 'Unknow error');
                }

                if ($code >= 32000 && $code <= 32099) {
                    $error->set('message', $this->errors[$code] ?? $error->message);
                }
            }
            $this->error = $error;
        }
    }
    public const array errors = [
        32600 => 'Invalid Request',
        32601 => 'Method not found',
        32602 => 'Invalid params',
        32603 => 'Internal error',
        32700 => 'Parse error',
    ];

    public static function errorCodeExists(int $code): bool
    {
        return isset(static::errors[$code]);
    }

    public static function errorFromCode(int $code, array|string|null $data = null): self
    {
        $error = new self();
        $error->error = new ErrorDescriptor();
        if ($code > 0) {
            $code *= -1;
        }
        $values = ['code' => $code];
        if (!static::errorCodeExists(abs($code))) {
            if ($data && is_array($data)) {
                $data = json_encode($data);
            }
            $values['message'] = $data ?? 'Unknow error';
        } elseif ($data) {
            $values['data'] = $data;
        }
        $error->error->loadProperties($values);
        //return (new self())->set('error', ['code' => $code, 'message' => static::$errors[$code] ?? 'Unknow error']);
        return $error;
    }
}