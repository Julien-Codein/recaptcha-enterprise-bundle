<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Tests\Form;

use Artack\RecaptchaEnterpriseBundle\Form\RecaptchaEnterpriseType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

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
        self::assertNull($view->vars['action_name']);
        self::assertSame('en', $view->vars['locale']);
        self::assertNull($view->vars['script_csp_nonce']);
    }

    public function testOptionsArePassedToTheView(): void
    {
        $view = $this->factory->create(RecaptchaEnterpriseType::class, null, [
            'action_name' => 'contact',
            'locale' => 'fr',
            'script_csp_nonce' => 'a-nonce',
        ])->createView();

        self::assertSame('contact', $view->vars['action_name']);
        self::assertSame('fr', $view->vars['locale']);
        self::assertSame('a-nonce', $view->vars['script_csp_nonce']);
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

    private function getFormFactoryWith(bool $enabled): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addType(new RecaptchaEnterpriseType(self::SITE_KEY, $enabled))
            ->getFormFactory()
        ;
    }
}
