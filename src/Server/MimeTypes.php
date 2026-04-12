<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server;

enum MimeTypes
{
    case HTML;
    case CSS;
    case JS;
    case JSON;
    case XML;
    case TXT;
    case CSV;
    case TSV;

    case JPG;
    case PNG;
    case GIF;
    case SVG;
    case ICO;
    case WEBP;
    case AVIF;
    case BMP;
    case TIFF;

    case PDF;
    case ZIP;
    case RAR;
    case TAR;
    case GZ;
    case BZ2;
    case SEVEN_Z;

    case DOC;
    case DOCX;
    case XLS;
    case XLSX;
    case PPT;
    case PPTX;

    case MP3;
    case WAV;
    case OGG;
    case M4A;
    case AAC;
    case FLAC;

    case MP4;
    case WEBM;
    case MOV;
    case AVI;
    case MKV;
    case MPEG;

    case WOFF;
    case WOFF2;
    case TTF;
    case OTF;
    case EOT;
    case SFNT;
    case TTC;

    case WASM;
    case BIN;

    public function mime(): string
    {
        return MimeTypeDefinitions::mime($this);
    }

    public function extension(): string
    {
        return MimeTypeDefinitions::extension($this);
    }

    public function contentType(?string $charset = null): string
    {
        $mime = $this->mime();

        if ($charset !== null && $this->isTextBased()) {
            return $mime . '; charset=' . $charset;
        }

        return $mime;
    }

    public function isTextBased(): bool
    {
        return MimeTypeDefinitions::isTextBased($this);
    }

    public static function fromExtension(string $extension): self
    {
        return MimeTypeDefinitions::fromExtension($extension);
    }

    public static function fromFile(string $file): self
    {
        return MimeTypeDefinitions::fromFile($file);
    }
}