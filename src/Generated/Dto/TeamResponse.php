<?php

namespace Amazeelabs\PolydockAppAmazeeioPrivateGpt\Generated\Dto;

/**
 * TeamResponse
 *
 * Auto-generated from OpenAPI specification. Do not edit manually.
 * @see https://api.amazee.ai/openapi.json
 */
final readonly class TeamResponse
{
    public function __construct(
        /**
         * Name
         */
        public string $name,
        /**
         * Admin Email
         */
        public string $admin_email,
        /**
         * Id
         */
        public int $id,
        /**
         * Is Active
         */
        public bool $is_active,
        /**
         * Is Always Free
         */
        public bool $is_always_free,
        /**
         * Created At
         */
        public \DateTimeInterface $created_at,
        /**
         * Phone
         */
        public ?string $phone = null,
        /**
         * Billing Address
         */
        public ?string $billing_address = null,
        /**
         * Updated At
         */
        public ?\DateTimeInterface $updated_at = null,
        /**
         * Last Payment
         */
        public ?\DateTimeInterface $last_payment = null
    ) {}
}