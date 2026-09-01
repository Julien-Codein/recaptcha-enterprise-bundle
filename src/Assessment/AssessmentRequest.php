<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Assessment;

/**
 * What the caller wants assessed.
 *
 * The site key belongs here rather than to the gateway: it is part of the event Google assesses,
 * while the project only identifies the endpoint the gateway talks to.
 */
final class AssessmentRequest
{
    public function __construct(
        public string $siteKey,
        public string $token,
        public ?string $expectedAction = null,
        public ?string $userIpAddress = null,
        public ?string $userAgent = null,
        public ?string $requestedUri = null,
    ) {}
}
