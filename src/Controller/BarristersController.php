<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\BarristerDirectory;

final class BarristersController extends AppController
{
    public function suggestions()
    {
        $this->request->allowMethod(['get']);
        $matches = (new BarristerDirectory())->search($this->request->getQuery('q'));
        return $this->response
            ->withType('application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody(json_encode(['suggestions' => $matches], JSON_THROW_ON_ERROR));
    }
}
