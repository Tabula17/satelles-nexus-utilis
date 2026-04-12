<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server;

final class MimeTypeDefinitions
{
    private static ?array $extensionLookupCache = null;

    public static function mime(MimeTypes $type): string
    {
        return self::definitions()[$type->name]['mime'] ?? 'application/octet-stream';
    }

    public static function extension(MimeTypes $type): string
    {
        return self::definitions()[$type->name]['extension'] ?? 'bin';
    }

    public static function fromExtension(string $extension): MimeTypes
    {
        $extension = strtolower(ltrim($extension, '.'));

        return self::extensionLookup()[$extension] ?? MimeTypes::BIN;
    }

    public static function fromFile(string $file): MimeTypes
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if ($extension === '') {
            return MimeTypes::BIN;
        }

        return self::fromExtension($extension);
    }

    public static function isTextBased(MimeTypes $type): bool
    {
        return isset(self::textBasedSet()[$type->name]);
    }

    private static function extensionLookup(): array
    {
        if (self::$extensionLookupCache !== null) {
            return self::$extensionLookupCache;
        }

        $lookup = [];

        foreach (self::definitions() as $name => $definition) {
            $case = constant(MimeTypes::class . '::' . $name);

            foreach ($definition['extensions'] as $extension) {
                $lookup[$extension] = $case;
            }
        }

        self::$extensionLookupCache = $lookup;

        return self::$extensionLookupCache;
    }

    private static function textBasedSet(): array
    {
        return [
            'HTML' => true,
            'CSS' => true,
            'JS' => true,
            'JSON' => true,
            'XML' => true,
            'TXT' => true,
            'CSV' => true,
            'TSV' => true,
            'SVG' => true,
        ];
    }

    private static function definitions(): array
    {
        return [
            'HTML' => [
                'mime' => 'text/html',
                'extensions' => ['html', 'htm'],
                'extension' => 'html',
            ],
            'CSS' => [
                'mime' => 'text/css',
                'extensions' => ['css'],
                'extension' => 'css',
            ],
            'JS' => [
                'mime' => 'application/javascript',
                'extensions' => ['js', 'mjs'],
                'extension' => 'js',
            ],
            'JSON' => [
                'mime' => 'application/json',
                'extensions' => ['json'],
                'extension' => 'json',
            ],
            'XML' => [
                'mime' => 'application/xml',
                'extensions' => ['xml'],
                'extension' => 'xml',
            ],
            'TXT' => [
                'mime' => 'text/plain',
                'extensions' => ['txt'],
                'extension' => 'txt',
            ],
            'CSV' => [
                'mime' => 'text/csv',
                'extensions' => ['csv'],
                'extension' => 'csv',
            ],
            'TSV' => [
                'mime' => 'text/tab-separated-values',
                'extensions' => ['tsv'],
                'extension' => 'tsv',
            ],
            'JPG' => [
                'mime' => 'image/jpeg',
                'extensions' => ['jpg', 'jpeg'],
                'extension' => 'jpg',
            ],
            'PNG' => [
                'mime' => 'image/png',
                'extensions' => ['png'],
                'extension' => 'png',
            ],
            'GIF' => [
                'mime' => 'image/gif',
                'extensions' => ['gif'],
                'extension' => 'gif',
            ],
            'SVG' => [
                'mime' => 'image/svg+xml',
                'extensions' => ['svg'],
                'extension' => 'svg',
            ],
            'ICO' => [
                'mime' => 'image/x-icon',
                'extensions' => ['ico'],
                'extension' => 'ico',
            ],
            'WEBP' => [
                'mime' => 'image/webp',
                'extensions' => ['webp'],
                'extension' => 'webp',
            ],
            'AVIF' => [
                'mime' => 'image/avif',
                'extensions' => ['avif'],
                'extension' => 'avif',
            ],
            'BMP' => [
                'mime' => 'image/bmp',
                'extensions' => ['bmp'],
                'extension' => 'bmp',
            ],
            'TIFF' => [
                'mime' => 'image/tiff',
                'extensions' => ['tif', 'tiff'],
                'extension' => 'tif',
            ],
            'PDF' => [
                'mime' => 'application/pdf',
                'extensions' => ['pdf'],
                'extension' => 'pdf',
            ],
            'ZIP' => [
                'mime' => 'application/zip',
                'extensions' => ['zip'],
                'extension' => 'zip',
            ],
            'RAR' => [
                'mime' => 'application/vnd.rar',
                'extensions' => ['rar'],
                'extension' => 'rar',
            ],
            'TAR' => [
                'mime' => 'application/x-tar',
                'extensions' => ['tar'],
                'extension' => 'tar',
            ],
            'GZ' => [
                'mime' => 'application/gzip',
                'extensions' => ['gz'],
                'extension' => 'gz',
            ],
            'BZ2' => [
                'mime' => 'application/x-bzip2',
                'extensions' => ['bz2'],
                'extension' => 'bz2',
            ],
            'SEVEN_Z' => [
                'mime' => 'application/x-7z-compressed',
                'extensions' => ['7z'],
                'extension' => '7z',
            ],
            'DOC' => [
                'mime' => 'application/msword',
                'extensions' => ['doc'],
                'extension' => 'doc',
            ],
            'DOCX' => [
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extensions' => ['docx'],
                'extension' => 'docx',
            ],
            'XLS' => [
                'mime' => 'application/vnd.ms-excel',
                'extensions' => ['xls'],
                'extension' => 'xls',
            ],
            'XLSX' => [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extensions' => ['xlsx'],
                'extension' => 'xlsx',
            ],
            'PPT' => [
                'mime' => 'application/vnd.ms-powerpoint',
                'extensions' => ['ppt'],
                'extension' => 'ppt',
            ],
            'PPTX' => [
                'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'extensions' => ['pptx'],
                'extension' => 'pptx',
            ],
            'MP3' => [
                'mime' => 'audio/mpeg',
                'extensions' => ['mp3'],
                'extension' => 'mp3',
            ],
            'WAV' => [
                'mime' => 'audio/wav',
                'extensions' => ['wav'],
                'extension' => 'wav',
            ],
            'OGG' => [
                'mime' => 'audio/ogg',
                'extensions' => ['ogg'],
                'extension' => 'ogg',
            ],
            'M4A' => [
                'mime' => 'audio/mp4',
                'extensions' => ['m4a'],
                'extension' => 'm4a',
            ],
            'AAC' => [
                'mime' => 'audio/aac',
                'extensions' => ['aac'],
                'extension' => 'aac',
            ],
            'FLAC' => [
                'mime' => 'audio/flac',
                'extensions' => ['flac'],
                'extension' => 'flac',
            ],
            'MP4' => [
                'mime' => 'video/mp4',
                'extensions' => ['mp4'],
                'extension' => 'mp4',
            ],
            'WEBM' => [
                'mime' => 'video/webm',
                'extensions' => ['webm'],
                'extension' => 'webm',
            ],
            'MOV' => [
                'mime' => 'video/quicktime',
                'extensions' => ['mov'],
                'extension' => 'mov',
            ],
            'AVI' => [
                'mime' => 'video/x-msvideo',
                'extensions' => ['avi'],
                'extension' => 'avi',
            ],
            'MKV' => [
                'mime' => 'video/x-matroska',
                'extensions' => ['mkv'],
                'extension' => 'mkv',
            ],
            'MPEG' => [
                'mime' => 'video/mpeg',
                'extensions' => ['mpeg', 'mpg'],
                'extension' => 'mpg',
            ],
            'WOFF' => [
                'mime' => 'font/woff',
                'extensions' => ['woff'],
                'extension' => 'woff',
            ],
            'WOFF2' => [
                'mime' => 'font/woff2',
                'extensions' => ['woff2'],
                'extension' => 'woff2',
            ],
            'TTF' => [
                'mime' => 'font/ttf',
                'extensions' => ['ttf'],
                'extension' => 'ttf',
            ],
            'OTF' => [
                'mime' => 'font/otf',
                'extensions' => ['otf'],
                'extension' => 'otf',
            ],
            'EOT' => [
                'mime' => 'application/vnd.ms-fontobject',
                'extensions' => ['eot'],
                'extension' => 'eot',
            ],
            'SFNT' => [
                'mime' => 'font/sfnt',
                'extensions' => ['sfnt'],
                'extension' => 'sfnt',
            ],
            'TTC' => [
                'mime' => 'font/collection',
                'extensions' => ['ttc'],
                'extension' => 'ttc',
            ],
            'WASM' => [
                'mime' => 'application/wasm',
                'extensions' => ['wasm'],
                'extension' => 'wasm',
            ],
            'BIN' => [
                'mime' => 'application/octet-stream',
                'extensions' => [],
                'extension' => 'bin',
            ],
        ];
    }
}