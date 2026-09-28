<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;

final class AdminUsersController extends AppController
{
    public function login()
    {
        if ($this->request->is('post')) {
            $username = (string)Configure::read('Foleys.adminUsername', '');
            $password = (string)Configure::read('Foleys.adminPassword', '');
            if ($username !== '' && $password !== '' && hash_equals($username, (string)$this->request->getData('username')) && hash_equals($password, (string)$this->request->getData('password'))) {
                $this->request->getSession()->write('Foleys.admin', $username);
                return $this->redirect('/');
            }
            $this->Flash->error('The administrator details are not valid, or an administrator account has not been configured yet.');
        }
    }

    public function logout()
    {
        $this->request->getSession()->delete('Foleys.admin');
        $this->Flash->success('You have signed out of administrator mode.');
        return $this->redirect('/');
    }
}
