<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderPricingService;
use App\Service\NotificationService;
use Cake\I18n\FrozenTime;

final class OrdersController extends AppController
{
    public function quote()
    {
        $items = $this->activeItems(); $pricing = null;
        if ($this->request->is('post')) {
            $pricing = (new OrderPricingService())->calculate($this->submittedItems($items), $this->activeRules());
            if ($pricing['lines'] === []) { $this->Flash->error('Add at least one item before viewing a quote.'); }
        }
        $this->set(compact('items', 'pricing'));
    }

    public function add()
    {
        $items = $this->activeItems();
        if ($this->request->is('post')) { $this->submit($items); }
        $this->set(compact('items'));
    }

    public function received() {}

    private function submit(array $items): void
    {
        $submitted = $this->submittedItems($items);
        if ($submitted === []) { $this->Flash->error('Add at least one item to your request.'); return; }
        $pricing = (new OrderPricingService())->calculate($submitted, $this->activeRules());
        $orders = $this->fetchTable('Orders');
        $order = $orders->patchEntity($orders->newEmptyEntity(), [
            'barrister_name' => trim((string)$this->request->getData('barrister_name')),
            'email' => trim((string)$this->request->getData('email')) ?: null,
            'notes' => trim((string)$this->request->getData('notes')) ?: null,
            'status' => 'submitted', 'subtotal_cents' => $pricing['subtotal'], 'discount_cents' => $pricing['discount'],
            'total_cents' => $pricing['total'], 'submitted_at' => FrozenTime::now(), 'created' => FrozenTime::now(),
        ]);
        if ($order->hasErrors()) { $this->set('order', $order); return; }
        $orderItems = $this->fetchTable('OrderItems');
        $orders->getConnection()->transactional(function () use ($orders, $orderItems, $order, $pricing): void {
            if (!$orders->save($order)) { throw new \RuntimeException('The collection request could not be saved.'); }
            foreach ($pricing['lines'] as $line) {
                if (!$orderItems->save($orderItems->newEntity($line + ['order_id' => $order->id, 'created' => FrozenTime::now()]))) { throw new \RuntimeException('An item in the collection request could not be saved.'); }
            }
        });
        $emailProblem = (new NotificationService())->sendRequestSubmitted($order, $pricing['lines']);
        if ($emailProblem !== null) {
            $this->Flash->warning($emailProblem);
        }
        $this->request->getSession()->write('Foleys.lastOrder', ['name' => $order->barrister_name, 'total' => $pricing['total']]);
        $this->redirect(['action' => 'received']);
    }

    private function activeItems(): array { return $this->fetchTable('ServiceItems')->find()->where(['active' => true])->orderBy(['position' => 'ASC'])->all()->toList(); }
    private function activeRules(): array { return $this->fetchTable('DiscountRules')->find()->where(['active' => true])->orderBy(['minimum_item_count' => 'ASC'])->all()->map(fn ($rule) => $rule->toArray())->toList(); }
    private function submittedItems(array $items): array
    {
        $catalogue = []; foreach ($items as $item) { $catalogue[(int)$item->id] = $item; }
        $submitted = [];
        foreach ((array)$this->request->getData('items') as $data) {
            $id = (int)($data['service_item_id'] ?? 0); $quantity = filter_var($data['quantity'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
            if ($quantity > 0 && isset($catalogue[$id])) { $item = $catalogue[$id]; $submitted[] = ['service_item_id' => $id, 'item_name' => $item->name, 'unit_price_cents' => (int)$item->unit_price_cents, 'quantity' => $quantity]; }
        }
        return $submitted;
    }
}
