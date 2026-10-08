<?php
/**
 * Flash messages and validation summary.
 *
 * Flash keys: success, error, warning, info, _errors, _old.
 */

$flashKeys = [
    'success' => ['alert-success', 'check'],
    'error'   => ['alert-error', 'alert'],
    'warning' => ['alert-warning', 'alert'],
    'info'    => ['alert-info', 'info'],
];

$icons = [
    'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>',
    'info'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>',
];

$renderAlerts = static function () use ($flashKeys, $icons): void {
    foreach ($flashKeys as $key => [$class, $icon]) {
        $message = flash($key);
        if ($message === null || $message === '' || !is_string($message)) {
            continue;
        }
        ?>
        <div class="wrap" style="padding-top:16px">
          <div class="alert <?= e($class) ?>" role="<?= $key === 'error' ? 'alert' : 'status' ?>">
            <?= $icons[$icon] ?>
            <p><?= e($message) ?></p>
          </div>
        </div>
        <?php
    }
};

$fieldErrors = errors();

$renderAlerts();
?>

<?php if ($fieldErrors !== []): ?>
  <div class="wrap" style="padding-top:16px">
    <div class="alert alert-error" role="alert">
      <?= $icons['alert'] ?>
      <div>
        <strong>Please check the form and try again.</strong>
        <ul>
          <?php foreach ($fieldErrors as $field => $messages): ?>
            <?php foreach ((array) $messages as $message): ?>
              <li><?= e((string) $message) ?></li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
<?php endif; ?>
