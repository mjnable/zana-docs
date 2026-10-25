<!doctype html>
<html lang="<?= $locale ?>">
<head>
  <meta charset="utf-8">
  <title><?= $title ?> | Acme</title>
<?php foreach (isset($page) ? $this->alternates($page) : [] as $language => $href): ?>
  <link rel="alternate" hreflang="<?= $language ?>" href="<?= $href ?>">
<?php endforeach ?>
</head>
<body>
  <nav>
    <a href="<?= $this->url('home') ?>"><?= $this->t('nav.home') ?></a>
    <a href="<?= $this->url('about') ?>"><?= $this->t('nav.about') ?></a>
    <a href="<?= $this->url('contact') ?>"><?= $this->t('nav.contact') ?></a>
<?php if (isset($page)): ?>
    <a class="language" href="<?= $this->url($page, ['_locale' => $locale === 'en' ? 'fr' : 'en']) ?>"><?= $this->t('nav.other_language') ?></a>
<?php endif ?>
  </nav>
  <main><?= $this->section('content') ?></main>
  <script nonce="<?= $csp_nonce ?>">document.documentElement.classList.add('js');</script>
</body>
</html>
