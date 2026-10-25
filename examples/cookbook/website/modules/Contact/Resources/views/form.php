<?php $this->layout('layouts/site', ['title' => $this->t('contact.title')]) ?>
<h1><?= $this->t('contact.title') ?></h1>
<form method="post" action="<?= $this->url('contact') ?>" novalidate>
  <?= $this->csrf_field('contact') ?>
<?php foreach (['name' => 'text', 'email' => 'email'] as $field => $type): ?>
  <label><?= $this->t("contact.{$field}") ?>
    <input type="<?= $type ?>" name="<?= $field ?>" value="<?= $old[$field] ?? '' ?>">
  </label>
<?php foreach ($errors[$field] ?? [] as $error): ?><p class="error"><?= $error ?></p><?php endforeach ?>
<?php endforeach ?>
  <label><?= $this->t('contact.message') ?>
    <textarea name="message"><?= $old['message'] ?? '' ?></textarea>
  </label>
<?php foreach ($errors['message'] ?? [] as $error): ?><p class="error"><?= $error ?></p><?php endforeach ?>
  <button><?= $this->t('contact.send') ?></button>
</form>
