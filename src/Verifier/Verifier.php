<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Verifier;

use Artack\RecaptchaEnterpriseBundle\Assessment\Assessment;
use Artack\RecaptchaEnterpriseBundle\Assessment\AssessmentRequest;
use Artack\RecaptchaEnterpriseBundle\Assessment\Exception\AssessmentExceptionInterface;
use Artack\RecaptchaEnterpriseBundle\Assessment\GatewayInterface;
use Artack\RecaptchaEnterpriseBundle\Assessment\InvalidReason;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decides what an assessment means. Every decision the bundle makes lives here, so a gateway only
 * ever has to translate.
 */
final class Verifier implements VerifierInterface
{
    private ?Result $result = null;

    public function __construct(
        private readonly GatewayInterface $gateway,
        private readonly string $siteKey,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $denyOnError = true,
    ) {}

    public function verify(string $token, ?string $expectedAction = null): Result
    {
        // Nothing to assess, and Google would only be asked to confirm it.
        if ('' === $token) {
            return $this->result = Result::unverified(InvalidReason::MISSING);
        }

        try {
            $assessment = $this->gateway->assess($this->createRequest($token, $expectedAction));
        } catch (AssessmentExceptionInterface $exception) {
            $this->logger?->error('The reCAPTCHA Enterprise assessment failed: {message}', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            // An unreachable API says nothing about the token, so it is reported as its own fact
            // rather than disguised as an invalid one.
            return $this->result = Result::unavailable($exception->getMessage(), !$this->denyOnError);
        }

        return $this->result = $this->createResult($assessment, $expectedAction);
    }

    public function getLatestResult(): ?Result
    {
        return $this->result;
    }

    private function createRequest(string $token, ?string $expectedAction): AssessmentRequest
    {
        $request = $this->requestStack?->getCurrentRequest();

        return new AssessmentRequest(
            $this->siteKey,
            $token,
            $expectedAction,
            $request?->getClientIp(),
            $request?->headers->get('User-Agent'),
        );
    }

    private function createResult(Assessment $assessment, ?string $expectedAction): Result
    {
        $invalidReason = $assessment->invalidReason;

        // A token minted for another action could otherwise be replayed here. Google reports this
        // itself when it is given the expected action, but the comparison is cheap and the bundle
        // must not depend on the gateway having sent it.
        $actionMatches = null === $expectedAction || $assessment->action === $expectedAction;

        if ($assessment->valid && !$actionMatches) {
            $invalidReason = InvalidReason::UNEXPECTED_ACTION;
        }

        return new Result(
            $assessment->valid && $actionMatches,
            $assessment->valid,
            $assessment->action,
            $assessment->score,
            $invalidReason,
            $assessment->raw,
        );
    }
}
