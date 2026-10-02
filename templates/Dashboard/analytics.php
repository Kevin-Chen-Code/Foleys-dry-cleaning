<?php $this->assign('title', 'Analytics'); ?>
<section class="page-heading">
    <h1>BI analytics</h1>
    <p> Current service performance.</p>
</section>

<div class="steps">
    <article>
        <h2><?= (int)$metrics['orders'] ?></h2>
        <p>Requests received</p>
    </article>
    <article>
        <h2><?= (int)$metrics['garments'] ?></h2>
        <p>Garments processed</p>
    </article>
    <article>
        <h2>$<?= number_format($metrics['revenue']/100, 2) ?></h2>
        <p>Revenue recorded</p>
    </article>
</div>
