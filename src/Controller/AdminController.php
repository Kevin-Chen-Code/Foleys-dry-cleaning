<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;

abstract class AdminController extends AppController
{
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        if (!$this->request->getSession()->read('Foleys.admin')) {
            $this->Flash->error('Please sign in as an administrator first.');
            $event->setResult($this->redirect('/admin/login'));
            $event->stopPropagation();
        }
    }
}
