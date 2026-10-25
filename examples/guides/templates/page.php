<?php $this->layout('layout', ['heading' => $this->upper($this->raw('title'))]) ?>
<p><?= $intro ?></p>
<ul><?php foreach ($tags as $tag): ?><li><?= $tag ?></li><?php endforeach ?></ul>
