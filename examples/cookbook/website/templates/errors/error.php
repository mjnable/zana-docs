<?php $this->layout('layouts/site', ['title' => $this->t('error.title', ['status' => $status])]) ?>
<h1><?= $this->t('error.title', ['status' => $status]) ?></h1>
<p><?= $this->t($status === 404 ? 'error.not_found' : 'error.other') ?></p>
<p><a href="<?= $this->url('home') ?>"><?= $this->t('nav.home') ?></a></p>
