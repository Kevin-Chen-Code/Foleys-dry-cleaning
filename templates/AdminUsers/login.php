<?php $this->assign('title', 'Administrator sign in'); ?>
<section class="notice-panel" style="max-width:480px;margin:3rem auto">
    <p class="eyebrow">Administrator mode</p>
    <h1>Sign in</h1>
    <p>Enter your administrator credentials to manage requests, prices, and reports.</p>
    <?= $this->Form->create() ?>
    <?= $this->Form->control('username', ['required' => true, 'autocomplete' => 'username']) ?>
    <?= $this->Form->control('password', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <?= $this->Form->button('Sign in', ['class' => 'button-primary']) ?>
    <?= $this->Form->end() ?>
</section>
