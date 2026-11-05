<?php /** Request confirmation: all amounts are discounted charges, with no tax added. */ ?>
<div style="font-family:Arial,sans-serif;font-size:16px;line-height:1.5;color:#243746">
    <p>Dear <?= h($firstName) ?>,</p>
    <p>Thank you for submitting your dry-cleaning request. Your submitted items are listed below.</p>
    <table cellpadding="10" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:760px">
        <thead>
            <tr style="background-color:#17324d;color:#ffffff;text-align:left">
                <th scope="col">Submitted (Melbourne date)</th>
                <th scope="col">Item type</th>
                <th scope="col">Quantity</th>
                <th scope="col">Discounted cost</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lines as $index => $line): ?>
            <tr>
                <td style="border-bottom:1px solid #dce3ea"><?= h($submittedDate) ?></td>
                <td style="border-bottom:1px solid #dce3ea"><?= h($line['item_name']) ?></td>
                <td style="border-bottom:1px solid #dce3ea"><?= (int)$line['quantity'] ?></td>
                <td style="border-bottom:1px solid #dce3ea">$<?= number_format(($discountedCosts[$index] ?? 0) / 100, 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p><strong>Total: $<?= number_format((int)$order->total_cents / 100, 2) ?> AUD</strong></p>
    <p>Kind regards,<br>Foley’s List</p>
</div>
