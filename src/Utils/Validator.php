<?php

declare(strict_types=1);

namespace App\Utils;

use App\Command\GenerateThumbnailCommand;
use Symfony\Component\Console\Exception\InvalidArgumentException;

final class Validator
{
    const PATH_TYPE = [
        GenerateThumbnailCommand::FILE_TYPE,
        GenerateThumbnailCommand::DIRECTORY_TYPE
    ];

    const STORAGE_TYPE = [
        GenerateThumbnailCommand::STORAGE_LOCAL_TYPE,
        GenerateThumbnailCommand::STORAGE_AMAZON_S3_TYPE,
        GenerateThumbnailCommand::STORAGE_DROPBOX_TYPE
    ];

    public function validatePath (?string $path): string
    {
        if (empty($path)) {
            throw new InvalidArgumentException('The path can not be empty');
        }

        return $path;
    }

    public function validateType (?string $type): string
    {
        if (empty($type)) {
            throw new InvalidArgumentException('The path type can not be empty');
        }

        if (!in_array($type, self::PATH_TYPE)) {
            throw new InvalidArgumentException(sprintf('The path type must be one of <%s>', implode(', ', self::PATH_TYPE)));
        }

        return $type;
    }

    public function validateStorage (?string $storage): string
    {
        if (empty($storage)) {
            throw new InvalidArgumentException('The storage can not be empty');
        }

        if (!in_array($storage, self::STORAGE_TYPE)) {
            throw new InvalidArgumentException(sprintf('The storage type must be one of <%s>', implode(', ', self::STORAGE_TYPE)));
        }

        return $storage;
    }
}