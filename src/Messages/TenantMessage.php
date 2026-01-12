<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Oured\MultiTenant\Messages;

/**
 * TenantMessage
 *
 * A message DTO that includes tenant context for message broker communication.
 * Use this to ensure tenant information is always included when publishing
 * and consuming messages between services.
 *
 * Usage:
 *   // Publishing
 *   $message = TenantMessage::create('payment.created', ['id' => 123]);
 *   $broker->publish($message->toJson());
 *
 *   // Consuming
 *   $message = TenantMessage::fromJson($rawMessage);
 *   $context->setTenantById($message->tenantId);
 */
class TenantMessage
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $eventType,
        public readonly array $payload = [],
        public readonly array $metadata = []
    ) {}

    /**
     * Create a message with the current tenant context.
     */
    public static function create(string $eventType, array $payload = [], array $metadata = []): self
    {
        $context = app(\Oured\MultiTenant\Tenancy\TenantContext::class);
        $tenantId = $context->getTenantId();

        if (! $tenantId) {
            throw new \RuntimeException('Cannot create TenantMessage without tenant context');
        }

        return new self(
            tenantId: $tenantId,
            eventType: $eventType,
            payload: $payload,
            metadata: array_merge([
                'created_at' => now()->toIso8601String(),
                'source' => config('app.name', 'unknown'),
            ], $metadata)
        );
    }

    /**
     * Create a message for a specific tenant.
     */
    public static function forTenant(string $tenantId, string $eventType, array $payload = [], array $metadata = []): self
    {
        return new self(
            tenantId: $tenantId,
            eventType: $eventType,
            payload: $payload,
            metadata: array_merge([
                'created_at' => now()->toIso8601String(),
                'source' => config('app.name', 'unknown'),
            ], $metadata)
        );
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['tenant_id'])) {
            throw new \InvalidArgumentException('Message must contain tenant_id');
        }

        if (! isset($data['event_type'])) {
            throw new \InvalidArgumentException('Message must contain event_type');
        }

        return new self(
            tenantId: $data['tenant_id'],
            eventType: $data['event_type'],
            payload: $data['payload'] ?? [],
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Create from JSON string.
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Get a value from the payload.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * Get a value from the metadata.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if this is a specific event type.
     */
    public function isType(string $eventType): bool
    {
        return $this->eventType === $eventType;
    }

    /**
     * Get the source service that created this message.
     */
    public function getSource(): ?string
    {
        return $this->metadata['source'] ?? null;
    }

    /**
     * Get when the message was created.
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        $createdAt = $this->metadata['created_at'] ?? null;

        if (! $createdAt) {
            return null;
        }

        return new \DateTimeImmutable($createdAt);
    }
}

