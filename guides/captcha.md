# Captcha

`mj-zana/captcha` (2.25) asks a CAPTCHA provider whether a person sent a
form: Cloudflare Turnstile, Google reCAPTCHA (v2, and v3 with a score) or
hCaptcha, chosen by configuration. It is the provider behind
mj-zana/security's `#[VerifyHuman]`.

```
composer require mj-zana/captcha symfony/http-client nyholm/psr7
```

Use it when a form is attacked despite the honeypot, the time trap and the
spam rules, or when bots race people for a purchase, such as tickets the
moment sales open.

- **Configuration:** `captcha.provider` (`turnstile`, `recaptcha_v2`,
  `recaptcha_v3`, `hcaptcha`), with `captcha.secret` and `captcha.site_key`
  from the environment (`APP_CAPTCHA__SECRET`, `APP_CAPTCHA__SITE_KEY`).
  Changing provider is changing that setting.
- **A PSR-18 client** asks the provider: install one and register it as
  `Psr\Http\Client\ClientInterface`, with a connection timeout, a total
  duration and no retries, e.g. Symfony's `Psr18Client` around
  `HttpClient::create(['max_connect_duration' => 2, 'timeout' => 3, 'max_duration' => 5])`
  (`max_connect_duration`: Symfony 8.1 or later). No provider
  SDK.
- **The widget:** `<?= $this->captcha_widget('contact', $csp_nonce) ?>` in
  the form prints the provider's script and widget, escaped.
- **`check`** says when the widget cannot work: no provider, secret or site
  key (`captcha.config`), no HTTP client (`captcha.http_client`), or a
  Content-Security-Policy that blocks it (`captcha.csp`), with what to add.
  A blocked widget sends no token, so every visitor would be refused.

How it fits with the rest, and testing without the network:
[security](security.md#new-in-225-bot-verification).
