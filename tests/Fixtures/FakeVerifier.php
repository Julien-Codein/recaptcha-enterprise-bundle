<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Tests\Fixtures;

use Artack\RecaptchaEnterpriseBundle\Verifier\Result;
use Artack\RecaptchaEnterpriseBundle\Verifier\VerifierInterface;

final class FakeVerifier implements VerifierInterface
{
    public ?string $lastToken = null;
    public ?string $lastExpectedAction = null;
    private ?Result $result = null;

    public function __construct(
        private Result $nextResult,
    ) {}

    public function setNextResult(Result $result): void
    {
        $this->nextResult = $result;
    }

    public function verify(string $token, ?string $expectedAction = null): Result
    {
        $this->lastToken = $token;
        $this->lastExpectedAction = $expectedAction;

        return $this->result = $this->nextResult;
    }

    public function getLatestResult(): ?Result
    {
        return $this->result;
    }
}
