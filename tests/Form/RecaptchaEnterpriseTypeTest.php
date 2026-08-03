<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Tests\Form;

use Artack\RecaptchaEnterpriseBundle\Form\RecaptchaEnterpriseType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

/**
 * @internal
 */
#[CoversClass(RecaptchaEnterpriseType::class)]
final class RecaptchaEnterpriseTypeTest extends TypeTestCase
{
    private const SITE_KEY = 'a-site-key';

    public function testDefaults(): void
    {
        $view = $this->factory->create(RecaptchaEnterpriseType::class)->createView();

        self::assertSame(self::SITE_KEY, $view->vars['site_key']);
        self::assertTrue($view->vars['enabled']);
        self::assertSame('score', $view->vars['challenge']);
        self::assertNull($view->vars['action_name']);
        self::assertNull($view->vars['locale']);
        self::assertSame('light', $view->vars['theme']);
        self::assertSame('normal', $view->vars['size']);
        self::assertNull($view->vars['script_csp_nonce']);
    }

    public function testOptionsArePassedToTheView(): void
    {
        $view = $this->factory->create(RecaptchaEnterpriseType::class, null, [
            'action_name' => 'contact',
            'theme' => 'dark',
            'size' => 'compact',
            'script_csp_nonce' => 'a-nonce',
        ])->createView();

        self::assertSame('contact', $view->vars['action_name']);
        self::assertSame('dark', $view->vars['theme']);
        self::assertSame('compact', $view->vars['size']);
        self::assertSame('a-nonce', $view->vars['script_csp_nonce']);
    }

    public function testTheConfiguredChallengeBecomesTheDefault(): void
    {
        $factory = $this->getFormFactoryWith(challenge: RecaptchaEnterpriseType::CHALLENGE_CHECKBOX);

        $view = $factory->create(RecaptchaEnterpriseType::class)->createView();

        self::assertSame('checkbox', $view->vars['challenge']);
    }

    /**
     * The challenge and the locale belong to the page, not the field: one enterprise.js load per
     * page, one render= and one hl= value. A field overriding the challenge would also keep the
     * single global site key, so it would send a key of the wrong type and Google would refuse it.
     */
    #[DataProvider('provideTheChallengeAndTheLocaleCannotBeSetPerFieldCases')]
    public function testTheChallengeAndTheLocaleCannotBeSetPerField(string $option, string $value): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->factory->create(RecaptchaEnterpriseType::class, null, [$option => $value]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTheChallengeAndTheLocaleCannotBeSetPerFieldCases(): iterable
    {
        yield 'challenge' => ['challenge', 'checkbox'];

        yield 'locale' => ['locale', 'fr'];
    }

    public function testTheConfiguredLocaleIsExposedToTheView(): void
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addType(new RecaptchaEnterpriseType(self::SITE_KEY, true, RecaptchaEnterpriseType::CHALLENGE_SCORE, 'fr'))
            ->getFormFactory()
        ;

        self::assertSame('fr', $factory->create(RecaptchaEnterpriseType::class)->createView()->vars['locale']);
    }

    public function testAnUnknownThemeIsRejected(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(RecaptchaEnterpriseType::class, null, ['theme' => 'blue']);
    }

    public function testAnUnknownSizeIsRejected(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(RecaptchaEnterpriseType::class, null, ['size' => 'huge']);
    }

    public function testANonStringActionNameIsRejected(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(RecaptchaEnterpriseType::class, null, ['action_name' => 42]);
    }

    public function testTheFieldIsHiddenAndUnmapped(): void
    {
        $type = new RecaptchaEnterpriseType(self::SITE_KEY, true);

        self::assertSame(HiddenType::class, $type->getParent());
        self::assertSame('recaptcha_enterprise', $type->getBlockPrefix());

        $form = $this->factory->create(RecaptchaEnterpriseType::class);

        self::assertFalse($form->getConfig()->getMapped());
    }

    public function testDisabledBundleIsExposedToTheView(): void
    {
        $factory = $this->getFormFactoryWith(enabled: false);

        $view = $factory->create(RecaptchaEnterpriseType::class)->createView();

        self::assertFalse($view->vars['enabled']);
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        return [new PreloadedExtension([new RecaptchaEnterpriseType(self::SITE_KEY, true)], [])];
    }

    private function getFormFactoryWith(
        bool $enabled = true,
        string $challenge = RecaptchaEnterpriseType::CHALLENGE_SCORE,
    ): FormFactoryInterface {
        return Forms::createFormFactoryBuilder()
            ->addType(new RecaptchaEnterpriseType(self::SITE_KEY, $enabled, $challenge))
            ->getFormFactory()
        ;
    }
}
