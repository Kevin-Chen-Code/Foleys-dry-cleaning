<?php
/** @var \App\View\AppView $this */
$this->assign('title', 'Request received');
$this->Html->css('item-dialog', ['block' => true]);
$request = $this->request->getSession()->consume('Foleys.lastOrder');
?>
<div class="success-overlay"><section class="success-dialog">
    <p class="eyebrow">Request received</p><h1>Thank you<?= $request ? ', ' . h($request['name']) : '' ?>.</h1>
    <p>Your dry-cleaning collection request has been sent to the admin team.</p>
    <?php if ($request): ?><p class="total-confirmation">Total: $<?= number_format($request['total'] / 100, 2) ?></p><?php endif; ?>
    <?= $this->Html->link('Back to home page', '/', ['class' => 'dialog-action']) ?>
</section></div>
