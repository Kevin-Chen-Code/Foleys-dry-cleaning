<?php /** Request confirmation: all amounts are discounted charges, with no tax added. */ ?>
<div style="font-family:Arial,sans-serif;font-size:16px;line-height:1.5;color:#222222;background-color:#ffffff">
    <p style="margin:0 0 18px">Dear <?= h($firstName) ?>,</p>
    <p style="margin:0 0 18px">Thank you for submitting your dry-cleaning request. Your submitted items are listed below.</p>
    <table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;border:1px solid #333333;text-align:left">
        <thead>
            <tr style="background-color:#ffffff;color:#222222;text-align:left">
                <th scope="col" style="border:1px solid #333333;padding:8px">Submitted (Melbourne)</th>
                <th scope="col" style="border:1px solid #333333;padding:8px">Item type</th>
                <th scope="col" style="border:1px solid #333333;padding:8px">Quantity</th>
                <th scope="col" style="border:1px solid #333333;padding:8px">Discounted cost (AUD)</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lines as $index => $line): ?>
            <tr>
                <td style="border:1px solid #333333;padding:8px"><?= h($submittedDate) ?></td>
                <td style="border:1px solid #333333;padding:8px"><?= h($line['item_name']) ?></td>
                <td style="border:1px solid #333333;padding:8px"><?= (int)$line['quantity'] ?></td>
                <td style="border:1px solid #333333;padding:8px">$<?= number_format(($discountedCosts[$index] ?? 0) / 100, 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin:18px 0"><strong>Total: $<?= number_format((int)$order->total_cents / 100, 2) ?> AUD</strong></p>
    <p style="margin:0">Kind regards,<br>Foley’s List</p>
</div>
