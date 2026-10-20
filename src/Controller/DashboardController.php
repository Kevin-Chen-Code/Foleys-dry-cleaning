<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\WeeklyReportService;
use App\Service\NotificationService;
use App\Model\Table\DiscountRulesTable;
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
                // Keep existing display order; append new garments automatically.
                $position = $id ? (int)$entity->position : (int)($itemsTable->find()->select(['position'])->orderBy(['position' => 'DESC'])->first()?->position ?? 0) + 1;
                $itemsTable->patchEntity($entity, ['name' => trim((string)$this->request->getData('name')), 'description' => trim((string)$this->request->getData('description')) ?: null, 'unit_price_cents' => (int)round((float)$this->request->getData('price') * 100), 'active' => (bool)$this->request->getData('active'), 'price_cap_eligible' => (bool)$this->request->getData('price_cap_eligible'), 'position' => $position, 'created' => $id ? $entity->created : FrozenTime::now()]);
                $itemsTable->save($entity);
            } elseif ($action === 'delete_rule') {
                $rulesTable->delete($rulesTable->get((int)$this->request->getData('id')));
            } elseif ($action === 'save_rule') {
                $id = (int)$this->request->getData('id');
                $entity = $id ? $rulesTable->get($id) : $rulesTable->newEmptyEntity();
                $type = (string)$this->request->getData('discount_type');
                $value = DiscountRulesTable::formValueToStorage($this->request->getData('discount_value'), $type);
                if ($value === null) {
                    $this->Flash->error('Enter a percentage from 0 to 100, or a dollar amount with at most two decimal places.');
                    return $this->redirect(['action' => 'managePricing']);
                }
                $rulesTable->patchEntity($entity, ['name' => trim((string)$this->request->getData('name')), 'minimum_item_count' => $this->request->getData('minimum_item_count'), 'discount_type' => $type, 'discount_value' => $value, 'active' => true, 'created' => $id ? $entity->created : FrozenTime::now()]);
                if (!$rulesTable->save($entity)) {
                    $this->Flash->error('Discount rule could not be saved. Enter a name and a minimum item count of at least 1.');
                    return $this->redirect(['action' => 'managePricing']);
                }
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
        ['orders' => $orders, 'itemsByOrder' => $itemsByOrder, 'discountedCostsByOrder' => $discountedCostsByOrder, 'cutoff' => $cutoff, 'asOf' => $asOf] = (new WeeklyReportService())->data();
        $emailDelivery = (new NotificationService())->deliveryStatus();
        $this->set(compact('orders', 'itemsByOrder', 'discountedCostsByOrder', 'cutoff', 'asOf', 'settings', 'emailDelivery'));
    }

    public function testEmail()
    {
        $this->request->allowMethod(['post']);
        $notifications = new NotificationService();
        if (!$notifications->deliveryStatus()['ready']) {
            $this->Flash->error('Complete the email delivery configuration shown in Email settings before sending a test.');
        } elseif ($notifications->sendTestEmail()) {
            $this->Flash->success('The email provider accepted the test email for your saved mailbox. Check its inbox and Resend delivery status.');
        } else {
            $this->Flash->error('The test email could not be sent. Check the saved mailbox and the delivery error in the server logs.');
        }
        return $this->redirect(['action' => 'weeklyReport']);
    }

    public function weeklyReportCsv()
    {
        // Keep old bookmarks working, but do not disguise Excel bytes as CSV.
        return $this->redirect('/admin/weekly-report.xlsx');
    }

    public function weeklyReportExcel()
    {
        return $this->response->withHeader('Content-Type', WeeklyReportService::EXCEL_MIME)
            ->withDownload('foleys-weekly-report.xlsx')
            ->withStringBody((new WeeklyReportService())->xlsx());
    }
}
