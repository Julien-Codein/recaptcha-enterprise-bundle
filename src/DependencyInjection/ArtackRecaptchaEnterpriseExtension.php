<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class ArtackRecaptchaEnterpriseExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{enabled: bool, site_key: string, project_id: string, api_key: string, min_score: float} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('artack_recaptcha_enterprise.enabled', $config['enabled']);
        $container->setParameter('artack_recaptcha_enterprise.site_key', $config['site_key']);
        $container->setParameter('artack_recaptcha_enterprise.project_id', $config['project_id']);
        $container->setParameter('artack_recaptcha_enterprise.api_key', $config['api_key']);
        $container->setParameter('artack_recaptcha_enterprise.min_score', $config['min_score']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        // The form theme is only useful with Twig, and prepending to a missing extension throws.
        if (!$container->hasExtension('twig')) {
            return;
        }

        $container->prependExtensionConfig('twig', [
            'form_themes' => ['@ArtackRecaptchaEnterprise/Form/recaptcha_enterprise_widget.html.twig'],
        ]);
    }

    public function getAlias(): string
    {
        return 'artack_recaptcha_enterprise';
    }
}
