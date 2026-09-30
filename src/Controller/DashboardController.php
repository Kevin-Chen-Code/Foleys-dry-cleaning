<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\FrozenTime;

final class DashboardController extends AdminController
{
    public function analytics()
    {
        $orders = $this->fetchTable('Orders')->find()->all()->toList();
        $orderItems = $this->fetchTable('OrderItems')->find()->all()->toList();
        $this->set('metrics', ['orders' => count($orders), 'revenue' => array_sum(array_map(fn($order) => (int)$order->total_cents, $orders)), 'garments' => array_sum(array_map(fn($item) => (int)$item->quantity, $orderItems))]);
    }

    public function incomingRequests()
    {
        $orders = $this->fetchTable('Orders')->find()->orderBy(['submitted_at' => 'DESC'])->all()->toList();
        $this->set(compact('orders'));
    }

    public function managePricing()
    {
        $itemsTable = $this->fetchTable('ServiceItems');
        $rulesTable = $this->fetchTable('DiscountRules');
        if ($this->request->is('post')) {
            $action = (string)$this->request->getData('action');
            if ($action === 'delete_item') {
                $entity = $itemsTable->get((int)$this->request->getData('id'));
                $itemsTable->delete($entity);
            } elseif ($action === 'save_item') {
                $id = (int)$this->request->getData('id');
                $entity = $id ? $itemsTable->get($id) : $itemsTable->newEmptyEntity();
                $itemsTable->patchEntity($entity, ['name' => trim((string)$this->request->getData('name')), 'description' => trim((string)$this->request->getData('description')) ?: null, 'unit_price_cents' => (int)round((float)$this->request->getData('price') * 100), 'active' => (bool)$this->request->getData('active'), 'position' => (int)$this->request->getData('position'), 'created' => $id ? $entity->created : FrozenTime::now()]);
                $itemsTable->save($entity);
            } elseif ($action === 'delete_rule') {
                $rulesTable->delete($rulesTable->get((int)$this->request->getData('id')));
            } elseif ($action === 'save_rule') {
                $id = (int)$this->request->getData('id');
                $entity = $id ? $rulesTable->get($id) : $rulesTable->newEmptyEntity();
                $rulesTable->patchEntity($entity, ['name' => trim((string)$this->request->getData('name')), 'minimum_item_count' => (int)$this->request->getData('minimum_item_count'), 'discount_type' => (string)$this->request->getData('discount_type'), 'discount_value' => (int)$this->request->getData('discount_value'), 'active' => true, 'created' => $id ? $entity->created : FrozenTime::now()]);
                $rulesTable->save($entity);
            }
            $this->Flash->success('Pricing configuration saved.');
            return $this->redirect($this->request->getRequestTarget());
        }
        $items = $itemsTable->find()->orderBy(['position' => 'ASC'])->all()->toList();
        $rules = $rulesTable->find()->all()->toList();
        $this->set(compact('items', 'rules'));
    }

    public function weeklyReport()
    {
        $orders = $this->fetchTable('Orders')->find()->orderBy(['submitted_at' => 'DESC'])->all()->toList();
        $items = $this->fetchTable('OrderItems')->find()->all()->toList();
        $itemsByOrder = [];
        foreach ($items as $item) { $itemsByOrder[(int)$item->order_id][] = $item; }
        $this->set(compact('orders', 'itemsByOrder'));
    }

    public function weeklyReportCsv()
    {
        $orders = $this->fetchTable('Orders')->find()->orderBy(['submitted_at' => 'DESC'])->all()->toList();
        $items = $this->fetchTable('OrderItems')->find()->all()->toList();
        $itemsByOrder = [];
        foreach ($items as $item) { $itemsByOrder[(int)$item->order_id][] = $item; }
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['Barrister', 'Submitted', 'Item type', 'Quantity', 'Cost']);
        foreach ($orders as $order) {
            foreach ($itemsByOrder[(int)$order->id] ?? [] as $item) {
                fputcsv($stream, [$order->barrister_name, $order->submitted_at?->format('Y-m-d'), $item->item_name, $item->quantity, number_format($item->line_total_cents / 100, 2, '.', '')]);
            }
        }
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        return $this->response->withType('csv')->withDownload('foleys-weekly-report.csv')->withStringBody($csv ?: '');
    }
}
