<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Files;

/**
 * Book A CORE-10 BR-CORE-10-002/AC-CORE-10-001. Detects the real MIME
 * type from file content (`finfo`, magic bytes) — never trusts the
 * filename extension or a client-supplied `Content-Type` header. A
 * `.exe` renamed to `.jpg` is caught here, not by looking at its name.
 */
final class MimeTypeInspector
{
    public function detect(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mime !== false ? $mime : 'application/octet-stream';
    }

    public function detectFromContents(string $contents): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = finfo_buffer($finfo, $contents);
        finfo_close($finfo);

        return $mime !== false ? $mime : 'application/octet-stream';
    }
}
