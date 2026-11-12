<?php
declare(strict_types=1);
namespace InnovEvents\Http;
final class HttpError extends \RuntimeException
{
    public function __construct(public readonly int $status, string $code) { parent::__construct($code); }
}
