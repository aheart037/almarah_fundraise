<?php
/**
 * Intermediate page that auto-posts the stored TransactionID to the gateway's
 * hosted payment page. Shown when the gateway requires a POST hand-off.
 *
 * @var string $action
 * @var array $fields
 * @var string $gatewayLabel
 */
?>
<div class="bridge-shell">
  <div class="bridge-card">
    <div class="spinner" aria-hidden="true"></div>
    <h1>Taking you to the secure payment page</h1>
    <p>You are being handed over to <?= e($gatewayLabel) ?>. If nothing happens within a few seconds, press the button below.</p>

    <form method="post" action="<?= e($action) ?>" id="bridgeForm">
      <?php foreach ($fields as $name => $value): ?>
        <input type="hidden" name="<?= e((string) $name) ?>" value="<?= e((string) $value) ?>">
      <?php endforeach; ?>
      <button class="btn btn-brand btn-lg btn-block" type="submit">Continue to payment</button>
    </form>

    <p style="margin-top:18px;font-size:13px">
      Do not close this window. Your card details are entered only on the bank&rsquo;s own page.
    </p>
  </div>
</div>

<script>
  document.getElementById("bridgeForm").submit();
</script>
