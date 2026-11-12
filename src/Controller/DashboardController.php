<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\WeeklyReportService;
use App\Service\NotificationService;
use App\Model\Table\DiscountRulesTable;
use Cake\Http\Exception\BadRequestException;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;

final class DashboardController extends AdminController
{
    public function analytics()
    {
        $orders = $this->fetchTable('Orders')->find()->where(['status !=' => 'deleted'])->all()->toList();
        $barristers = [];
        foreach ($orders as $order) {
            $email = mb_strtolower(trim((string)$order->email));
            $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$order->barrister_name) ?? ''));
            if ($email !== '') {
                $barristers['email:' . $email] = true;
            } elseif ($name !== '') {
                $barristers['name:' . $name] = true;
            }
        }
        $ids = array_map(static fn ($order): int => (int)$order->id, $orders);
        $orderItems = $ids === [] ? [] : $this->fetchTable('OrderItems')->find()->where(['order_id IN' => $ids])->all()->toList();
        $this->set('metrics', ['orders' => count($orders), 'uniqueBarristers' => count($barristers), 'revenue' => array_sum(array_map(fn($order) => (int)$order->total_cents, $orders)), 'garments' => array_sum(array_map(fn($item) => (int)$item->quantity, $orderItems))]);
    }

    public function incomingRequests()
    {
        $this->setResponse($this->response->withHeader('Cache-Control', 'no-store, private'));
        $showDeleted = $this->request->getQuery('deleted') === '1';
        $filter = $showDeleted ? ['status' => 'deleted'] : ['status !=' => 'deleted'];
        $orders = $this->fetchTable('Orders')->find()->where($filter)->orderBy(['submitted_at' => 'DESC'])->all()->toList();
        $this->set(compact('orders', 'showDeleted'));
    }

    public function deleteRequest()
    {
        $this->request->allowMethod(['post']);
        $id = filter_var($this->request->getData('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new BadRequestException('Invalid request ID.');
        }

        // Retain the request and all item rows so accidental removals can be restored.
        $deleted = $this->fetchTable('Orders')->updateAll(['status' => 'deleted'], ['id' => $id, 'status !=' => 'deleted']);
        if ($deleted === 0) {
            $this->Flash->success('This request has already been removed.');
        } else {
            Log::info(sprintf('Request %d removed by administrator %s; retained for restoration.', $id, (string)$this->request->getSession()->read('Foleys.admin')));
            $this->Flash->success('Request removed. It can be restored from Removed requests.');
        }

        return $this->redirect(['action' => 'incomingRequests'], 303);
    }

    public function restoreRequest()
    {
        $this->request->allowMethod(['post']);
        $id = filter_var($this->request->getData('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new BadRequestException('Invalid request ID.');
        }
        $restored = $this->fetchTable('Orders')->updateAll(['status' => 'submitted'], ['id' => $id, 'status' => 'deleted']);
        if ($restored > 0) {
            Log::info(sprintf('Request %d restored by administrator %s.', $id, (string)$this->request->getSession()->read('Foleys.admin')));
        }
        $this->Flash->success($restored > 0 ? 'Request restored.' : 'Request is already restored or could not be found.');
        return $this->redirect(['action' => 'incomingRequests'], 303);
    }

    public function managePricing()
    {
        $itemsTable = $this->fetchTable('ServiceItems');
        $rulesTable = $this->fetchTable('DiscountRules');
        if ($this->request->is('post')) {
            $action = (string)$this->request->getData('action');
            if ($action === 'delete_item') {
                $entity = $itemsTable->get((int)$this->request->getData('id'));
                if (!$itemsTable->removeFromCatalogue($entity)) {
                    $this->Flash->error($itemsTable->getSchema()->hasColumn('archived_at')
                        ? 'The garment could not be removed. Please try again.'
                        : 'This garment is still used by existing requests. Apply the catalogue archive migration before removing it. Existing requests have not been changed.');
                } else {
                    $this->Flash->success('Garment removed from the catalogue. Existing requests have been kept.');
                }
                return $this->redirect(['action' => 'managePricing']);
            } elseif ($action === 'save_item') {
                $id = (int)$this->request->getData('id');
                $entity = $id ? $itemsTable->find('catalogue')->where(['id' => $id])->firstOrFail() : $itemsTable->newEmptyEntity();
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
                $qualifier = trim((string)$this->request->getData('qualifying_item_name')) ?: null;
                if ($type === 'unit_price' && $qualifier === null) {
                    $this->Flash->error('Enter the garment name for a unit-price offer.');
                    return $this->redirect(['action' => 'managePricing']);
                }
                $rulesTable->patchEntity($entity, ['name' => trim((string)$this->request->getData('name')), 'minimum_item_count' => $this->request->getData('minimum_item_count'), 'discount_type' => $type, 'discount_value' => $value, 'qualifying_item_name' => $qualifier, 'active' => true, 'created' => $id ? $entity->created : FrozenTime::now()]);
                if (!$rulesTable->save($entity)) {
                    $this->Flash->error('Discount rule could not be saved. Enter a name and a minimum item count of at least 1.');
                    return $this->redirect(['action' => 'managePricing']);
                }
            }
            $this->Flash->success('Pricing configuration saved.');
            return $this->redirect($this->request->getRequestTarget());
        }
        $items = $itemsTable->find('catalogue')->orderBy(['position' => 'ASC'])->all()->toList();
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
        ['orders' => $orders, 'itemsByOrder' => $itemsByOrder, 'discountedCostsByOrder' => $discountedCostsByOrder, 'asOf' => $asOf] = (new WeeklyReportService())->data();
        $this->setResponse($this->response->withHeader('Cache-Control', 'no-store, private'));
        $emailDelivery = (new NotificationService())->deliveryStatus();
        $this->set(compact('orders', 'itemsByOrder', 'discountedCostsByOrder', 'asOf', 'settings', 'emailDelivery'));
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
        // Downloads now require an explicit CSRF-protected POST because they reset the report.
        return $this->redirect('/admin/weekly-report');
    }

    public function weeklyReportExcel()
    {
        $this->request->allowMethod(['post']);
        return $this->response->withHeader('Content-Type', WeeklyReportService::EXCEL_MIME)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withDownload('foleys-weekly-report.xlsx')
            ->withStringBody((new WeeklyReportService())->downloadAndReset());
    }
}
