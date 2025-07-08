<?php

declare(strict_types=1);

namespace Shopologic\PSR\Http\Message;

/**
 * PSR-7: HTTP message interfaces - Uploaded File
 * 
 * Value object representing a file uploaded through an HTTP request.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 */
interface UploadedFileInterface
{
    /**
     * Retrieve a stream representing the uploaded file.
     *
     * @return StreamInterface Stream representation of the uploaded file.
     * @throws \RuntimeException in cases when no stream is available or can be created.
     */
    public function getStream(): StreamInterface;

    /**
     * Move the uploaded file to a new location.
     *
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws \InvalidArgumentException if the $targetPath specified is invalid.
     * @throws \RuntimeException on any error during the move operation, or on
     *     the second or subsequent call to the method.
     */
    public function moveTo(string $targetPath): void;

    /**
     * Retrieve the file size.
     *
     * @return int|null The file size in bytes or null if unknown.
     */
    public function getSize(): ?int;

    /**
     * Retrieve the error associated with the uploaded file.
     *
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public function getError(): int;

    /**
     * Retrieve the filename sent by the client.
     *
     * @return string|null The filename sent by the client or null if none was provided.
     */
    public function getClientFilename(): ?string;

    /**
     * Retrieve the media type sent by the client.
     *
     * @return string|null The media type sent by the client or null if none was provided.
     */
    public function getClientMediaType(): ?string;
}