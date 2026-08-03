<?php

declare(strict_types=1);

use Artack\RecaptchaEnterpriseBundle\Form\RecaptchaEnterpriseType;
use Artack\RecaptchaEnterpriseBundle\Service\IpResolver;
use Artack\RecaptchaEnterpriseBundle\Service\UserAgentResolver;
use Artack\RecaptchaEnterpriseBundle\Validator\RecaptchaEnterpriseValidator;
use Artack\RecaptchaEnterpriseBundle\Verifier\Verifier;
use Artack\RecaptchaEnterpriseBundle\Verifier\VerifierInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('artack_recaptcha_enterprise.ip_resolver', IpResolver::class)
        ->args([service('request_stack')])
    ;

    $services->set('artack_recaptcha_enterprise.user_agent_resolver', UserAgentResolver::class)
        ->args([service('request_stack')])
    ;

    $services->set('artack_recaptcha_enterprise.verifier', Verifier::class)
        ->args([
            '%artack_recaptcha_enterprise.project_id%',
            '%artack_recaptcha_enterprise.site_key%',
            '%artack_recaptcha_enterprise.api_key%',
            service('artack_recaptcha_enterprise.ip_resolver'),
            service('artack_recaptcha_enterprise.user_agent_resolver'),
        ])
    ;

    $services->alias(VerifierInterface::class, 'artack_recaptcha_enterprise.verifier');

    $services->set(RecaptchaEnterpriseValidator::class)
        ->args([
            service('artack_recaptcha_enterprise.verifier'),
            '%artack_recaptcha_enterprise.enabled%',
            '%artack_recaptcha_enterprise.min_score%',
        ])
        ->tag('validator.constraint_validator')
    ;

    $services->set(RecaptchaEnterpriseType::class)
        ->args([
            '%artack_recaptcha_enterprise.site_key%',
            '%artack_recaptcha_enterprise.enabled%',
        ])
        ->tag('form.type')
    ;
};
