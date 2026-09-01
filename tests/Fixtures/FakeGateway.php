<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Tests\Fixtures;

use Artack\RecaptchaEnterpriseBundle\Assessment\Assessment;
use Artack\RecaptchaEnterpriseBundle\Assessment\AssessmentRequest;
use Artack\RecaptchaEnterpriseBundle\Assessment\Exception\AssessmentExceptionInterface;
use Artack\RecaptchaEnterpriseBundle\Assessment\GatewayInterface;

/**
 * The port is one method, so a fake beats a mock: the Verifier tests can assert on the request
 * that was built without describing expectations up front.
 */
final class FakeGateway implements GatewayInterface
{
    public ?AssessmentRequest $lastRequest = null;
    public int $calls = 0;

    public function __construct(
        private readonly Assessment|AssessmentExceptionInterface $answer,
    ) {}

    public function assess(AssessmentRequest $request): Assessment
    {
        $this->lastRequest = $request;
        ++$this->calls;

        if ($this->answer instanceof AssessmentExceptionInterface) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
