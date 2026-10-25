<?php $this->layout('layouts/site', ['title' => $this->t('home.title')]) ?>
<h1><?= $this->t('home.title') ?></h1>
<p><?= $this->t('home.intro') ?></p>
<p><a href="<?= $this->url('contact') ?>"><?= $this->t('home.cta') ?></a></p>
