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
- Separates deciding from calling: a `Verifier` holding the policy, and a gateway port with a single HTTP
  implementation, so an unreachable API is never mistaken for an invalid token.

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
> `composer require artack/recaptcha-enterprise-bundle:0.2.*`, and read "Upgrading from 0.2.0" before moving on.

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
    on_error: deny # deny (default) or allow, see "When Google cannot be reached"

when@dev:
    artack_recaptcha_enterprise:
        enabled: false # disable reCAPTCHA in dev environments
```

`site_key`, `project_id` and `api_key` are required. `min_score` defaults to `0.5` and is used when a constraint
does not define its own threshold; set it to `0` to disable the score check entirely, which is what checkbox keys
without score based protection need.

### When Google cannot be reached

A network failure, a rate limit or a Google outage says nothing about the token, so the bundle treats it as its own
outcome instead of reporting a valid token as invalid. `on_error` decides what happens then:

| Value | Behaviour |
|---|---|
| `deny` (default) | The submission is refused. Safe, but a Google outage blocks every form. |
| `allow` | The submission passes without an assessment. Keeps forms working, and lets bots through while the outage lasts. |

Either way the failure is logged at error level, and the violation raised by `deny` carries
the `RecaptchaEnterprise::UNAVAILABLE_ERROR` code so it can be told apart from a genuinely refused token.

### Verification result

The last `Result` is available outside the validator through the `VerifierInterface` service:

```php
use Artack\RecaptchaEnterpriseBundle\Verifier\VerifierInterface;

public function __construct(private readonly VerifierInterface $verifier) {}

// ...
$result = $this->verifier->getLatestResult();

$result->success;              // whether the token may be accepted, score aside
$result->valid;                // what Google said about the token itself
$result->score;                // null when the assessment carried no risk analysis
$result->invalidReason;        // an InvalidReason enum case, or null
$result->getInvalidReasonName(); // e.g. "EXPIRED"
$result->error;                // set only when no assessment could be obtained at all
$result->raw;                  // the untouched payload
```

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

Architecture
------------

Verification is split in two, so that deciding and talking to Google never mix:

- `Verifier` holds every decision: the empty token short circuit, the expected action check, the score-free
  outage policy. It knows nothing about HTTP.
- `GatewayInterface` is the port to Google. An implementation only translates the wire format
  into an `Assessment` value object, and raises a domain exception when there is no assessment to translate.

`HttpGateway` is the one implementation, calling the REST Assessments API through Symfony's HTTP client.
The official `google/cloud-recaptcha-enterprise` SDK is deliberately not used: the bundle makes a single unary call,
for which the SDK adds only a protobuf and gRPC stack, and its `google/gax` dependency requires
`ramsey/uuid ^4`, which cannot be installed alongside applications held at `ramsey/uuid` 3.x — Ibexa 4.6 among
them. Another gateway can be added behind the port without the domain noticing.

The gateway throws, rather than returning a failed assessment, whenever Google did not answer with one:

| Exception | Cause |
|---|---|
| `TransportException` | Network failure, undecodable body, rate limit (429) or server error (5xx) — all transient |
| `AuthenticationException` | 401 or 403: a missing, wrong or unauthorised API key |
| `InvalidRequestException` | 400 or 404: an unknown project or a malformed event |

All three implement `AssessmentExceptionInterface`. `Verifier` catches it, so no exception ever escapes into form
validation.

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

Upgrading from 0.2.0
--------------------

`0.2.0` is the last release, and everything below changed since. The bundle is pre-1.0, so these breaks land
without a deprecation cycle.

**No configuration change is required.** `enabled`, `site_key`, `project_id`, `api_key` and `min_score` keep their
names and meanings, and `on_error` is new and optional. Applications that only declare the config, add the form
type and use the constraint have nothing to do.

### Behaviour

- **The score check now fails closed.** An assessment carrying no risk analysis used to pass the threshold
  silently; it is now refused. Set `min_score: 0` to keep the old behaviour, which is also what checkbox keys
  without score based protection need.
- **An unreachable Google is no longer reported as an invalid token.** It used to surface as a failed assessment;
  it is now its own outcome, governed by `on_error` and defaulting to `deny` — the same refusal as before, but
  distinguishable and logged.
- **Violations now carry context**: the `{{ reason }}` and `{{ score }}` parameters, the `Result` as the violation
  cause, and one of `RecaptchaEnterprise::INVALID_TOKEN_ERROR`, `LOW_SCORE_ERROR` or `UNAVAILABLE_ERROR`
  as the code. Custom messages using those placeholders keep working; nothing is required to keep the plain message.

### API

- **`Artack\RecaptchaEnterpriseBundle\Service\` is removed** — `IpResolver`, `IpResolverInterface`,
  `UserAgentResolver` and `UserAgentResolverInterface`. `Verifier` reads the client IP and User-Agent
  from the request stack directly. Applications that decorated or replaced those services must drop that wiring.
- **`Verifier` takes a `GatewayInterface`** instead of the project id, site key, API key and the two resolvers.
  Code relying on the container or on `VerifierInterface` is unaffected; code instantiating `Verifier` by hand
  must build a `HttpGateway` first.
- **`Result` changed shape.** `$success`, `$valid`, `$action`, `$score` and `$raw` are unchanged, but `$raw` moved
  in the constructor and `$invalidReason`, `$error` and `getInvalidReasonName()` were added. Build one with named
  arguments. `$invalidReason` is an `InvalidReason` enum case, not a string or an int.

### Requirements

- Symfony `5.4`, `6.4` and `7.4` are now supported alongside `8.x`; `7.0` to `7.3` are not, the supported 7.x line
  is the LTS. PHP stays at `^8.2`.
- `symfony/http-foundation` is now an explicit requirement. It was already installed in practice, through
  `symfony/framework-bundle`.

License
-------

This bundle is released under the [MIT License](LICENSE).
