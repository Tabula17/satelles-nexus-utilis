<?php

namespace Tabula17\Satelles\Utilis\Config;

use InvalidArgumentException;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

/**
 * 'enabled' => true, // Habilitar mTLS
 * 'ssl_cert_file' => '/path/to/service_a.crt', // Certificado del servidor
 * 'ssl_key_file' => '/path/to/service_a.key', // Clave privada del servidor
 * 'ssl_client_cert_file' => true,
 * 'ssl_verify_peer' => true,
 * 'ssl_allow_self_signed' => true // Si
 */

class TCPSSLConfig extends AbstractDescriptor
{
    protected(set) bool $enabled = true;
    protected(set) string $ssl_cert_file {
        set(string $value) {
            if(!file_exists(realpath($value))){
                throw new InvalidArgumentException("El archivo de certificado no existe");
            }
            $this->ssl_cert_file = realpath($value);
        }
    }
    protected(set) string $ssl_key_file {
        set(string $value) {
            if(!file_exists(realpath($value))){
                throw new InvalidArgumentException("El archivo de clave no existe");
            }
            $this->ssl_key_file = realpath($value);
        }
    }
    protected(set) bool $ssl_client_cert_file = true;
    protected(set) bool $ssl_verify_peer = true;
    protected(set) bool $ssl_allow_self_signed = true;



}