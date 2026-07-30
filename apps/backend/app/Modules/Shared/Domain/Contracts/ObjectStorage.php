<?php
namespace Modules\Shared\Domain\Contracts;
interface ObjectStorage
{
    public function put(string $path, mixed $stream, string $contentType, bool $private = true): void;
    public function temporaryUrl(string $path, int $expiresInSeconds = 300): string;
    public function delete(string $path): void;
}
