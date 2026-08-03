<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Tests\Validator;

use Artack\RecaptchaEnterpriseBundle\Tests\Fixtures\FakeVerifier;
use Artack\RecaptchaEnterpriseBundle\Validator\RecaptchaEnterprise;
use Artack\RecaptchaEnterpriseBundle\Validator\RecaptchaEnterpriseValidator;
use Artack\RecaptchaEnterpriseBundle\Verifier\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @internal
 */
#[CoversClass(RecaptchaEnterpriseValidator::class)]
#[CoversClass(RecaptchaEnterprise::class)]
final class RecaptchaEnterpriseValidatorTest extends ConstraintValidatorTestCase
{
    private FakeVerifier $verifier;
    private bool $enabled = true;
    private float $minScore = 0.5;

    public function testASuccessfulAssessmentRaisesNoViolation(): void
    {
        $this->expect(new Result(true, true, 'contact', 0.9, []));

        $this->validator->validate('a-token', new RecaptchaEnterprise(actionName: 'contact'));

        $this->assertNoViolation();
        self::assertSame('a-token', $this->verifier->lastToken);
        self::assertSame('contact', $this->verifier->lastExpectedAction);
    }

    public function testAFailedAssessmentRaisesAViolation(): void
    {
        $this->expect(new Result(false, false, null, null, []));

        $this->validator->validate('a-token', new RecaptchaEnterprise());

        $this->buildViolation('You may be sending automated requests.')->assertRaised();
    }

    public function testAScoreBelowTheGlobalThresholdRaisesAViolation(): void
    {
        $this->expect(new Result(true, true, null, 0.3, []));

        $this->validator->validate('a-token', new RecaptchaEnterprise());

        $this->buildViolation('You may be sending automated requests.')->assertRaised();
    }

    public function testTheConstraintThresholdOverridesTheGlobalOne(): void
    {
        $this->expect(new Result(true, true, null, 0.6, []));

        $this->validator->validate('a-token', new RecaptchaEnterprise(minScore: 0.7));

        $this->buildViolation('You may be sending automated requests.')->assertRaised();
    }

    public function testACustomMessageIsUsed(): void
    {
        $this->expect(new Result(false, false, null, null, []));

        $this->validator->validate('a-token', new RecaptchaEnterprise(message: 'Nope.'));

        $this->buildViolation('Nope.')->assertRaised();
    }

    public function testANullTokenIsAssessedAsAnEmptyString(): void
    {
        $this->expect(new Result(false, false, null, null, []));

        $this->validator->validate(null, new RecaptchaEnterprise());

        self::assertSame('', $this->verifier->lastToken);
        $this->buildViolation('You may be sending automated requests.')->assertRaised();
    }

    public function testADisabledBundleSkipsTheAssessment(): void
    {
        $this->enabled = false;
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate('a-token', new RecaptchaEnterprise());

        $this->assertNoViolation();
        self::assertNull($this->verifier->lastToken);
    }

    public function testANonStringValueIsRejected(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(42, new RecaptchaEnterprise());
    }

    protected function createValidator(): RecaptchaEnterpriseValidator
    {
        // The answer is chosen per test, after setUp() has already built the validator.
        $this->verifier = new FakeVerifier(new Result(false, false, null, null, []));

        return new RecaptchaEnterpriseValidator($this->verifier, $this->enabled, $this->minScore);
    }

    private function expect(Result $result): void
    {
        $this->verifier->setNextResult($result);
    }
}
