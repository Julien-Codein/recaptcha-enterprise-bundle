<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Verifier;

use Artack\RecaptchaEnterpriseBundle\Assessment\InvalidReason;

final readonly class Result
{
    /**
     * @param bool                 $success whether the token may be accepted, score aside
     * @param bool                 $valid   what Google said about the token itself
     * @param array<string, mixed> $raw     the untouched payload, empty when there was none
     * @param null|string          $error   set when no assessment could be obtained at all
     */
    public function __construct(
        public bool $success,
        public bool $valid,
        public ?string $action = null,
        public ?float $score = null,
        public ?InvalidReason $invalidReason = null,
        public array $raw = [],
        public ?string $error = null,
    ) {}

    /**
     * The token was refused without asking Google, because there was nothing to ask about.
     */
    public static function unverified(InvalidReason $invalidReason = InvalidReason::MISSING): self
    {
        return new self(false, false, invalidReason: $invalidReason);
    }

    /**
     * Google could not be asked. Whether that accepts or refuses the token is a policy decision
     * taken by the Verifier, so it is handed in rather than assumed here.
     */
    public static function unavailable(string $error, bool $accepted): self
    {
        return new self($accepted, false, error: $error);
    }

    /**
     * Human readable name of the invalid reason, e.g. "EXPIRED".
     */
    public function getInvalidReasonName(): ?string
    {
        return $this->invalidReason?->value;
    }
}
