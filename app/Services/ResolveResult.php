<?php

declare(strict_types=1);

namespace App\Services;

readonly class ResolveResult
{
    public function __construct(
        public array  $rates,
        public string $dataSource,
    ) {}
}
