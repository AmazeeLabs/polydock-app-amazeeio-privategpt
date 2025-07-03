<?php

namespace Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto;

/**
 * HealthResponse
 *
 * Auto-generated from OpenAPI specification. Do not edit manually.
 * @see https://api.amazee.ai/openapi.json
 */
final readonly class HealthResponse
{
    public function __construct(
        /**
         * Health status
         */
        public string $status,
        /**
         * Timestamp
         */
        public string $timestamp,
        /**
         * API version
         */
        public string $version,
        /**
         * Uptime in seconds
         */
        public int $uptime,
        /**
         * Service status details
         * @var array<string, mixed>|null
         */
        public ?array $services = null
    ) {}
}