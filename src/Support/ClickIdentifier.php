<?php

namespace ElectricTomCat\GoogleAdsConversions\Support;

use Stringable;

/**
 * A click identifier that knows which kind it is.
 *
 * Google keeps gclid, gbraid and wbraid in three separate fields and rejects a
 * value placed in the wrong one:
 *
 *     The imported gclid could not be decoded. Make sure you use the correct
 *     gclid format.  at conversions[0].gclid
 *
 * That is easy to hit, because an untyped accessor like clickId() returns
 * whichever identifier the visitor happens to have. Store one of these instead
 * and the type travels with the value.
 */
final class ClickIdentifier implements Stringable
{
    public const GCLID = 'gclid';

    public const GBRAID = 'gbraid';

    public const WBRAID = 'wbraid';

    /**
     * Prefix shared by gbraid and wbraid values in the wild.
     *
     * A heuristic, not a documented format, so it is only ever used to warn
     * and to re-route a value that Google would otherwise reject outright.
     */
    private const BRAID_PREFIX = '0AAAAA';

    private function __construct(
        public readonly string $type,
        public readonly string $value,
    ) {}

    public static function gclid(string $value): self
    {
        return new self(self::GCLID, $value);
    }

    public static function gbraid(string $value): self
    {
        return new self(self::GBRAID, $value);
    }

    public static function wbraid(string $value): self
    {
        return new self(self::WBRAID, $value);
    }

    /**
     * @param  self::GCLID|self::GBRAID|self::WBRAID|string  $type
     */
    public static function make(string $type, string $value): self
    {
        return new self(
            in_array($type, [self::GCLID, self::GBRAID, self::WBRAID], true) ? $type : self::GCLID,
            $value,
        );
    }

    /**
     * Whether a value looks like a gbraid or wbraid rather than a gclid.
     *
     * gclids are long and begin with a base64url-encoded protobuf header
     * (`Cj`, `EAIaIQ`, and similar); braids are shorter and begin `0AAAAA`.
     */
    public static function looksLikeBraid(string $value): bool
    {
        return str_starts_with($value, self::BRAID_PREFIX);
    }

    public function isGclid(): bool
    {
        return $this->type === self::GCLID;
    }

    /**
     * The identifier as the three named arguments record() expects.
     *
     * @return array{gclid: string|null, gbraid: string|null, wbraid: string|null}
     */
    public function toArguments(): array
    {
        return [
            'gclid' => $this->type === self::GCLID ? $this->value : null,
            'gbraid' => $this->type === self::GBRAID ? $this->value : null,
            'wbraid' => $this->type === self::WBRAID ? $this->value : null,
        ];
    }

    /**
     * Rebuild from something previously persisted as "type:value".
     */
    public static function fromString(string $stored): ?self
    {
        if (! str_contains($stored, ':')) {
            return null;
        }

        [$type, $value] = explode(':', $stored, 2);

        return $value === '' ? null : self::make($type, $value);
    }

    /**
     * Round-trippable form, safe to keep in a single column.
     */
    public function __toString(): string
    {
        return $this->type.':'.$this->value;
    }
}
