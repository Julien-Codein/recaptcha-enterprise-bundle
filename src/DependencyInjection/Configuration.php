<?php

declare(strict_types=1);

namespace Artack\RecaptchaEnterpriseBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('artack_recaptcha_enterprise');

        $treeBuilder->getRootNode()
            ->children()
            ->booleanNode('enabled')
            ->info('Set to false to skip every assessment, e.g. in a test environment.')
            ->defaultTrue()
            ->end()
            ->scalarNode('site_key')
            ->info('The reCAPTCHA Enterprise site key, exposed to the browser.')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('project_id')
            ->info('The Google Cloud project owning the reCAPTCHA Enterprise key.')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('api_key')
            ->info('The Google Cloud API key authenticating the assessment calls.')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->floatNode('min_score')
            ->info('The lowest accepted risk analysis score. 0 disables the score check.')
            ->defaultValue(0.5)
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
