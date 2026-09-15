<?php

namespace Tabula17\Satelles\Utilis\Config;

use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

class SSLStreamConfig extends AbstractDescriptor
{
    /*
     *
     * peer_name string
Peer name to be used. If this value is not set, then the name is guessed based on the hostname used when opening the stream.

verify_peer bool
Require verification of SSL certificate used.Defaults to true.

verify_peer_name bool
Require verification of peer name.Defaults to true.

allow_self_signed bool
Allow self-signed certificates. Requires verify_peer.Defaults to false

cafile string
Location of Certificate Authority file on local filesystem which should be used with the verify_peer context option to authenticate the identity of the remote peer.

capath string
If cafile is not specified or if the certificate is not found there, the directory pointed to by capath is searched for a suitable certificate. capath must be a correctly hashed certificate directory.

local_cert string
Path to local certificate file on filesystem. It must be a PEM encoded file which contains your certificate and private key. It can optionally contain the certificate chain of issuers. The private key also may be contained in a separate file specified by local_pk.

local_pk string
Path to local private key file on filesystem in case of separate files for certificate (local_cert) and private key.

passphrase string
Passphrase with which your local_cert file was encoded.

verify_depth int
Abort if the certificate chain is too deep.Defaults to no verification.

ciphers string
Sets the list of available ciphers. The format of the string is described in » ciphers(1).Defaults to DEFAULT.

capture_peer_cert bool
If set to true a peer_certificate context option will be created containing the peer certificate.

capture_peer_cert_chain bool
If set to true a peer_certificate_chain context option will be created containing the certificate chain.

SNI_enabled bool
If set to true server name indication will be enabled. Enabling SNI allows multiple certificates on the same IP address.

disable_compression bool
If set, disable TLS compression. This can help mitigate the CRIME attack vector.

peer_fingerprint string | array
Aborts when the remote certificate digest doesn't match the specified hash.

When a string is used, the length will determine which hashing algorithm is applied, either "md5" (32) or "sha1" (40).

When an array is used, the keys indicate the hashing algorithm name and each corresponding value is the expected digest.

security_level int
Sets the security level. If not specified the library default security level is used. The security levels are described in » SSL_CTX_get_security_level(3).

     */
    public ?string $peer_name;
    public bool $verify_peer = true;
    public bool $verify_peer_name = true;
    public bool $allow_self_signed = false;
    public ?string $cafile = null;
    public ?string $capath = null;
    public ?string $local_cert = null;
    public ?string $local_pk = null;
    public ?string $passphrase = null;
    public ?int $verify_depth = null;
    /* asn1parse, ca, ciphers, cmp, cms, crl, crl2pkcs7, dgst, dhparam, dsa, dsaparam, ec, ecparam, enc, engine, errstr, gendsa, genpkey, genrsa, info, kdf, mac, nseq, ocsp, passwd, pkcs12, pkcs7, pkcs8, pkey, pkeyparam, pkeyutl, prime, rand, rehash, req, rsa, rsautl, s_client, s_server, s_time, sess_id, smime, speed, spkac, srp, storeutl, ts, verify, version, x509 */
    public ?string $ciphers = null;
    public bool $capture_peer_cert = false;
    public bool $capture_peer_cert_chain = false;
    public bool $SNI_enabled = false;
    public bool $disable_compression = false;
    public string|array|null $peer_fingerprint = null;
    public ?int $security_level = null {
        set {
            if ($value >= 0 && $value <= 5) {
                $this->security_level = $value;
            }
        }
    }
}