<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OrderPricingService;
use App\Service\NotificationService;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;

final class OrdersController extends AppController
{
    public function quote()
    {
        $items = $this->catalogueItems(); $pricing = null;
        if ($this->request->is('post')) {
            $pricing = (new OrderPricingService(Configure::read('Foleys.discountsEnabled', false) === true))->calculate($this->submittedItems($items), $this->activeRules());
            if ($pricing['lines'] === []) { $this->Flash->error('Add at least one item before viewing a quote.'); }
        }
        $this->set(compact('items', 'pricing'));
    }

    public function add()
    {
        $items = $this->catalogueItems();
        if ($this->request->is('post')) { $this->submit($items); }
        $rules = $this->activeRules();
        $this->set(compact('items', 'rules'));
    }

    public function received() {}

    private function submit(array $items): void
    {
        $submitted = $this->submittedItems($items);
        if ($submitted === []) { $this->Flash->error('Add at least one item to your request.'); return; }
        $pricing = (new OrderPricingService(Configure::read('Foleys.discountsEnabled', false) === true))->calculate($submitted, $this->activeRules());
        $orders = $this->fetchTable('Orders');
        $order = $orders->patchEntity($orders->newEmptyEntity(), [
            'barrister_name' => $this->request->getData('barrister_name'),
            'email' => $this->request->getData('email'),
            'notes' => trim((string)$this->request->getData('notes')) ?: null,
            'status' => 'submitted', 'subtotal_cents' => $pricing['subtotal'], 'discount_cents' => $pricing['discount'],
            'total_cents' => $pricing['total'], 'submitted_at' => FrozenTime::now(), 'created' => FrozenTime::now(),
        ]);
        if ($order->hasErrors()) { $this->set('order', $order); return; }
        $orderItems = $this->fetchTable('OrderItems');
        $hasLineCharges = $orderItems->getSchema()->hasColumn('discounted_total_cents');
        $orders->getConnection()->transactional(function () use ($orders, $orderItems, $order, $pricing, $hasLineCharges): void {
            if (!$orders->save($order)) { throw new \RuntimeException('The collection request could not be saved.'); }
            foreach ($pricing['lines'] as $line) {
                if (!$hasLineCharges) { unset($line['discounted_total_cents']); }
                if (!$orderItems->save($orderItems->newEntity($line + ['order_id' => $order->id, 'created' => FrozenTime::now()]))) { throw new \RuntimeException('An item in the collection request could not be saved.'); }
            }
        });
        $emailProblem = (new NotificationService())->sendRequestSubmitted($order, $pricing['lines']);
        if ($emailProblem !== null) {
            $this->Flash->warning($emailProblem);
        }
        $this->request->getSession()->write('Foleys.lastOrder', ['total' => $pricing['total'], 'emailProblem' => $emailProblem]);
        $this->redirect(['action' => 'received']);
    }

    private function catalogueItems(): array { return $this->fetchTable('ServiceItems')->find('catalogue')->orderBy(['position' => 'ASC', 'id' => 'ASC'])->all()->toList(); }
    private function activeRules(): array
    {
        if (Configure::read('Foleys.discountsEnabled', false) !== true) {
            return [];
        }
        return $this->fetchTable('DiscountRules')->find()->where(['active' => true])->orderBy(['minimum_item_count' => 'ASC', 'id' => 'ASC'])->all()->map(fn ($rule) => $rule->toArray())->toList();
    }
    private function submittedItems(array $items): array
    {
        $catalogue = []; foreach ($items as $item) { $catalogue[(int)$item->id] = $item; }
        $submitted = [];
        $totalQuantity = 0;
        foreach ((array)$this->request->getData('items') as $data) {
            $id = (int)($data['service_item_id'] ?? 0); $quantity = filter_var($data['quantity'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
            if ($quantity > OrderPricingService::MAX_ITEM_COUNT - $totalQuantity) {
                $this->Flash->error('A request may contain at most 10,000 garments. Please split larger requests.');
                return [];
            }
            if ($quantity > 0) { $totalQuantity += $quantity; }
            if ($quantity > 0 && isset($catalogue[$id])) { $item = $catalogue[$id]; $submitted[] = ['service_item_id' => $id, 'item_name' => $item->name, 'unit_price_cents' => (int)$item->unit_price_cents, 'quantity' => $quantity, 'price_cap_eligible' => OrderPricingService::isPriceCapEligible($item->toArray())]; }
        }
        return $submitted;
    }
}
