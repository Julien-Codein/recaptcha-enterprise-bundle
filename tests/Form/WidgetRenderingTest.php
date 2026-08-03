<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\Tests\Form;

use Artack\RecaptchaEnterpriseBundle\Form\RecaptchaEnterpriseType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function dirname;

/**
 * Renders the shipped form theme, which is the only part of the bundle the end user sees.
 *
 * The site key is deliberately free of dashes: Twig's `e('js')` escapes them to `-`, which
 * would make the assertions unreadable rather than reveal anything about the template.
 *
 * @internal
 */
#[CoversNothing]
final class WidgetRenderingTest extends TestCase
{
    private const SITE_KEY = 'asitekey123';
    private const THEME = 'Form/recaptcha_enterprise_widget.html.twig';

    private FormFactoryInterface $factory;
    private FormRenderer $renderer;

    protected function setUp(): void
    {
        $this->boot();
    }

    public function testTheHiddenFieldAndTheLoaderAreRendered(): void
    {
        $html = $this->render();

        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('https://www.google.com/recaptcha/enterprise.js?render='.self::SITE_KEY, $html);
        self::assertStringContainsString("grecaptcha.enterprise.execute('".self::SITE_KEY."'", $html);
    }

    public function testTheActionNameIsRendered(): void
    {
        $html = $this->render(['action_name' => 'contact']);

        self::assertStringContainsString("action: 'contact'", $html);
    }

    public function testTheLocaleIsPassedToTheLoader(): void
    {
        $html = $this->render(['locale' => 'fr']);

        self::assertStringContainsString('hl=fr', $html);
    }

    public function testCspNonceIsAppliedToEveryScript(): void
    {
        $html = $this->render(['script_csp_nonce' => 'anonce123']);

        self::assertSame(2, mb_substr_count($html, 'nonce="anonce123"'));
    }

    public function testDisabledBundleRendersOnlyTheHiddenField(): void
    {
        $this->boot(enabled: false);

        $html = $this->render();

        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    private function boot(bool $enabled = true): void
    {
        $twig = new Environment(new FilesystemLoader([
            __DIR__.'/../../src/Resources/views',
            dirname((new ReflectionClass(FormExtension::class))->getFileName() ?: '').'/../Resources/views/Form',
        ]));
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new TranslationExtension());

        $engine = new TwigRendererEngine(['form_div_layout.html.twig', self::THEME], $twig);
        $this->renderer = new FormRenderer($engine);

        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => fn (): FormRenderer => $this->renderer,
        ]));

        $this->factory = Forms::createFormFactoryBuilder()
            ->addType(new RecaptchaEnterpriseType(self::SITE_KEY, $enabled))
            ->getFormFactory()
        ;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function render(array $options = []): string
    {
        $view = $this->factory->create(RecaptchaEnterpriseType::class, null, $options)->createView();

        return $this->renderer->searchAndRenderBlock($view, 'widget');
    }
}
