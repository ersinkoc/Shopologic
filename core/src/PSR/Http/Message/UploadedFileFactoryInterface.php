<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Message;

/**
 * PSR-17: HTTP Factories - Uploaded File Factory
 * 
 * Factory for creating uploaded file instances.
 */
interface UploadedFileFactoryInterface
{
    /**
     * Create a new uploaded file.
     *
     * @param StreamInterface $stream Underlying stream representing the uploaded file content.
     * @param int|null $size File size in bytes.
     * @param int $error PHP file upload error code.
     * @param string|null $clientFilename Filename as provided by the client.
     * @param string|null $clientMediaType Media type as provided by the client.
     * @return UploadedFileInterface
     * @throws \InvalidArgumentException If the file resource is not readable.
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface;
}