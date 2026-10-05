<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\WeeklyReportService;
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
        $settingsTable = $this->fetchTable('EmailSettings');
        $settings = $settingsTable->find()->first() ?? $settingsTable->newEmptyEntity();
        if ($this->request->is('post')) {
            $settingsTable->patchEntity($settings, [
                'mailbox' => trim((string)$this->request->getData('mailbox')) ?: null,
                'reporting_day' => (string)$this->request->getData('reporting_day'),
                'created' => $settings->isNew() ? FrozenTime::now() : $settings->created,
                'updated' => FrozenTime::now(),
            ]);
            if ($settingsTable->save($settings)) {
                $this->Flash->success('Email settings saved.');
            } else {
                $this->Flash->error('Email settings could not be saved. Please check the mailbox address.');
            }
            return $this->redirect(['action' => 'weeklyReport']);
        }
        ['orders' => $orders, 'itemsByOrder' => $itemsByOrder, 'discountedCostsByOrder' => $discountedCostsByOrder] = (new WeeklyReportService())->data();
        $this->set(compact('orders', 'itemsByOrder', 'discountedCostsByOrder', 'settings'));
    }

    public function weeklyReportCsv()
    {
        $csv = (new WeeklyReportService())->csv();
        return $this->response->withType('csv')->withDownload('foleys-weekly-report.csv')->withStringBody($csv);
    }
}
