artack/recaptcha-enterprise-bundle
=================================

> Symfony integration for Google reCAPTCHA Enterprise (Assessments API).

[![Latest Release](https://img.shields.io/packagist/v/artack/recaptcha-enterprise-bundle.svg)](https://packagist.org/packages/artack/recaptcha-enterprise-bundle)
[![MIT License](https://img.shields.io/packagist/l/artack/recaptcha-enterprise-bundle.svg)](http://opensource.org/licenses/MIT)
[![Total Downloads](https://img.shields.io/packagist/dt/artack/recaptcha-enterprise-bundle.svg)](https://packagist.org/packages/artack/recaptcha-enterprise-bundle)

Developed by [ARTACK WebLab GmbH](https://www.artack.ch) in Zurich, Switzerland.

Features
--------

- Provides the **RecaptchaEnterpriseType** form type that renders the hidden token field, loads the Google script
  and submits the token transparently.
- Ships a **RecaptchaEnterprise** validation constraint for attributes and PHP configuration, including configurable
  score threshold and action names.
- Automatically resolves client IP and User-Agent from Symfony's request stack and forwards them to Google when
  available.
- Registers the form theme automatically, so no manual Twig configuration is required.

Requirements
------------

| Requirement | Supported versions |
|---|---|
| PHP | 8.2, 8.3, 8.4 |
| Symfony | 5.4 LTS, 6.4 LTS, 7.4 LTS, 8.x |

Every combination is covered by the CI matrix, together with a `--prefer-lowest` build that proves the declared minimums
actually install and work.

Symfony 5.4 is end of life upstream but supported here on purpose. Its components raise PHP deprecations that the bundle
cannot fix, so the test suite does not fail on deprecations.

Installation
------------

Install the bundle via [Composer](https://getcomposer.org):

```shell
$ composer require artack/recaptcha-enterprise-bundle
```

The bundle is auto-registered thanks to Symfony Flex support.

> ⚠️ This bundle is being used in production, but hasn't reached version 1.0 yet. Therefore, there can be breaking
> changes between minor versions. I'd recommend that you require the bundle only with the current minor version like
> `composer require artack/recapture-bundle:0.1.*`

Configuration
-------------

Create `config/packages/artack_recaptcha_enterprise.yaml` with your Google project credentials:

```yaml
# config/packages/artack_recaptcha_enterprise.yaml
artack_recaptcha_enterprise:
    enabled: '%env(resolve:ARTACK_GOOGLE_RECAPTCHA_ENABLED)%' # defaults to true
    site_key: '%env(resolve:ARTACK_GOOGLE_RECAPTCHA_SITE_KEY)%'
    project_id: '%env(resolve:ARTACK_GOOGLE_RECAPTCHA_PROJECT_ID)%'
    api_key: '%env(resolve:ARTACK_GOOGLE_RECAPTCHA_API_KEY)%'
    min_score: 0.5 # default score threshold used by the validator when none is provided

when@dev:
    artack_recaptcha_enterprise:
        enabled: false # disable reCAPTCHA in dev environments
```
All keys are required. `min_score` defaults to `0.5` and is used when a constraint does not define its own threshold.

Usage
-----

Render the token field in a Symfony form:

```php
use Artack\RecaptchaEnterpriseBundle\Form\RecaptchaEnterpriseType;
use Artack\RecaptchaEnterpriseBundle\Validator\RecaptchaEnterprise;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class)
            ->add('message', TextareaType::class)
            ->add('recaptchaToken', RecaptchaEnterpriseType::class, [
                'action_name' => 'contact', // sent to Google; also matched when validating
                'locale' => 'fr', // optional, defaults to 'en'; passed to the Google script as hl
                'script_csp_nonce' => '...', // optional generated nonce to be used in the script tag
                'constraints' => [
                    new RecaptchaEnterprise(
                        minScore: 0.7, // optional
                        actionName: 'contact',
                    ),
                ],
            ]);
    }
}
```

The provided Twig theme is prepended automatically. When the form submits, the bundle executes
`grecaptcha.enterprise.execute`, fills the hidden field and re-submits the form.

Development
-----------

Everything runs in Docker, so no local PHP or Composer is needed:

```shell
$ make install   # build the image and install the bundle and tool dependencies
$ make test      # run the test suite
$ make phpstan   # run the static analysis (level 9)
$ make cs        # check the coding standards
$ make cs-fix    # fix the coding standards
$ make qa        # run all of the above
```

The default stack is the lowest supported one, PHP 8.2 with `--prefer-lowest --prefer-stable`, which is what proves
the declared requirements hold. Override it to work against a newer stack:

```shell
$ make update-latest PHP_VERSION=8.4
$ make test PHP_VERSION=8.4
```

`composer.lock` is not committed. This is a library, so consumers resolve their own dependency versions and a committed
lock file would only mislead the matrix build.

License
-------

This bundle is released under the [MIT License](LICENSE).
