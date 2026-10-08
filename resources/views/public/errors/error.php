<?php
/**
 * Error page used for 4xx/5xx responses from the front controller.
 *
 * @var int $status
 * @var string $message
 */
?>
<section class="section">
  <div class="wrap">
    <div class="empty-state" style="padding:70px 20px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="width:64px;height:64px"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
      <h3><?= e($status === 403 ? 'You do not have access to this page' : 'Something went wrong') ?></h3>
      <p><?= e($message !== '' ? $message : 'Please try again. If the problem continues, contact support and quote error ' . $status . '.') ?></p>
      <div class="cta-actions">
        <a class="btn btn-brand" href="<?= e(base_url('/')) ?>">Back to home</a>
        <a class="btn btn-outline-brand" href="<?= e(base_url('support')) ?>">Contact support</a>
      </div>
      <p class="text-muted mt-3" style="font-size:13px">Reference: <?= e((string) $status) ?></p>
    </div>
  </div>
</section>
