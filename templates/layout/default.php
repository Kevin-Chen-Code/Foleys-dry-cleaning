<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */

$cakeDescription = "Foley's Dry Cleaning";
$isAdmin = (bool)$this->request->getSession()->read('Foleys.admin');
$isLoginPage = $this->request->getParam('controller') === 'AdminUsers' && $this->request->getParam('action') === 'login';
?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= $cakeDescription ?>:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css(['normalize.min', 'milligram.min', 'portal']) ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>

<body>
    <nav class="top-nav">
        <div class="top-nav-title">
            <a href="<?= $this->Url->build('/') ?>">FOLEY'S <span style="color: #2bc866">LIST</span><small>DRY CLEANING SERVICE</small></a>
        </div>
        <div class="top-nav-links">
            <?php if ($isAdmin): ?>
                <?= $this->Html->link('Incoming requests', '/admin/incoming-requests') ?>
                <?= $this->Html->link('Manage pricing', '/admin/manage-pricing') ?>
                <?= $this->Html->link('Weekly report', '/admin/weekly-report') ?>
                <?= $this->Html->link('Analytics', '/admin/analytics') ?>
                <?= $this->Html->link('Sign out as administrator', ['controller' => 'AdminUsers', 'action' => 'logout'], ['class' => 'admin-sign-in']) ?>
            <?php elseif (!$isLoginPage): ?>
                <?= $this->Html->link('Sign in as administrator', ['controller' => 'AdminUsers', 'action' => 'login'], ['class' => 'admin-sign-in']) ?>
            <?php endif; ?>
        </div>
    </nav>
    <main class="main">
        <div class="container">
            <?= $this->Flash->render() ?>
            <?= $this->fetch('content') ?>
        </div>
    </main>
    <footer>
        <h3>
            Foley's List dry cleaning portal:
        </h3>
        <p>
            A dedicated premium service for members of Foley's List, ensuring pristine court presentation and garment care daily.
        </p>
        <p>
            © 2026 Foley's List Pty Ltd. All rights reserved. 
        </p>
    </footer>
</body>
</html>
