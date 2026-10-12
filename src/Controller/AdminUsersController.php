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
            $submittedUsername = $this->request->getData('username');
            $submittedPassword = $this->request->getData('password');
            // Normalize usernames, but never trim or strip characters from a password.
            $submittedUsername = is_string($submittedUsername) ? trim($submittedUsername, ' ') : null;
            $validInput = is_string($submittedUsername)
                && $submittedUsername !== ''
                && strlen($submittedUsername) <= 254
                && preg_match('/[\x00-\x1F\x7F]/', $submittedUsername) === 0
                && is_string($submittedPassword)
                && $submittedPassword !== ''
                && strlen($submittedPassword) <= 4096
                && preg_match('/[\x00-\x1F\x7F]/', $submittedPassword) === 0;
            if ($validInput && $username !== '' && $password !== '' && hash_equals($username, $submittedUsername) && hash_equals($password, $submittedPassword)) {
                $this->request->getSession()->renew();
                $this->request->getSession()->write('Foleys.admin', $username);
                return $this->redirect('/');
            }
            $this->Flash->error('Incorrect username or password.');
        }
    }

    public function logout()
    {
        $this->request->getSession()->delete('Foleys.admin');
        $this->Flash->success('You have signed out of administrator mode.');
        return $this->redirect('/');
    }
}
