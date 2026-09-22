<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderPricingService;
use Cake\I18n\FrozenTime;

final class OrdersController extends AppController
{
    public function add()
    {
        $serviceItems = $this->fetchTable('ServiceItems');
        $items = $serviceItems->find()
            ->where(['active' => true])
            ->orderBy(['position' => 'ASC', 'name' => 'ASC'])
            ->all()
            ->toList();

        if ($this->request->is('post')) {
            $this->submit($items);
        }

        $this->set(compact('items'));
    }

    public function received()
    {
    }

    private function submit(array $items): void
    {
        $orders = $this->fetchTable('Orders');
        $orderItems = $this->fetchTable('OrderItems');
        $catalogue = [];
        foreach ($items as $item) {
            $catalogue[(int)$item->id] = $item;
        }

        $submittedItems = [];
        foreach ((array)$this->request->getData('items') as $id => $data) {
            $id = (int)$id;
            $quantity = filter_var($data['quantity'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
            if ($quantity > 0 && isset($catalogue[$id])) {
                $item = $catalogue[$id];
                $submittedItems[] = [
                    'service_item_id' => $id,
                    'item_name' => $item->name,
                    'unit_price_cents' => (int)$item->unit_price_cents,
                    'quantity' => $quantity,
                ];
            }
        }

        if ($submittedItems === []) {
            $this->Flash->error('Select a quantity for at least one item.');
            return;
        }

        $rule = $this->fetchTable('DiscountRules')->find()
            ->where(['active' => true])
            ->orderBy(['minimum_item_count' => 'ASC'])
            ->first();
        $pricing = (new OrderPricingService())->calculate($submittedItems, $rule?->toArray());
        $order = $orders->patchEntity($orders->newEmptyEntity(), [
            'barrister_name' => trim((string)$this->request->getData('barrister_name')),
            'email' => trim((string)$this->request->getData('email')) ?: null,
            'notes' => trim((string)$this->request->getData('notes')) ?: null,
            'status' => 'submitted',
            'subtotal_cents' => $pricing['subtotal'],
            'discount_cents' => $pricing['discount'],
            'total_cents' => $pricing['total'],
            'submitted_at' => FrozenTime::now(),
        ]);
        if ($order->hasErrors()) {
            $this->set('order', $order);
            return;
        }

        $connection = $orders->getConnection();
        $connection->transactional(function () use ($orders, $orderItems, $order, $pricing): void {
            if (!$orders->save($order)) {
                throw new \RuntimeException('The collection request could not be saved.');
            }
            foreach ($pricing['lines'] as $line) {
                $item = $orderItems->newEntity($line + ['order_id' => $order->id]);
                if (!$orderItems->save($item)) {
                    throw new \RuntimeException('An item in the collection request could not be saved.');
                }
            }
        });

        $this->request->getSession()->write('Foleys.lastOrder', [
            'name' => $order->barrister_name,
            'total' => $pricing['total'],
        ]);
        $this->redirect(['action' => 'received']);
    }
}
