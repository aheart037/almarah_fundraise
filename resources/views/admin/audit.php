<?php
/**
 * Append-only audit log.
 *
 * @var array $entries
 * @var int $total
 * @var int $page
 * @var int $lastPage
 * @var array $filters
 * @var array $entities
 */

$decode = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '';
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return '';
    }
    $parts = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        }
        $parts[] = $key . ': ' . (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value);
    }
    return implode(' · ', $parts);
};
?>
<div class="dash-head">
  <div>
    <h1>Audit log</h1>
    <p class="sub">An append-only record of every consequential action. Entries are never edited or deleted from here.</p>
  </div>
</div>

<div class="alert alert-info">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
  <p>Metadata is written through a redactor, so passwords, tokens, card fields and gateway credential parameters never reach this table.</p>
</div>

<section class="panel">
  <form class="toolbar" method="get" action="<?= e(base_url('admin/audit')) ?>">
    <div class="search-field">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <input class="input" type="search" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Action, entity or email">
    </div>
    <div class="select-field">
      <select class="select-field" name="entity" data-autosubmit aria-label="Entity type">
        <option value="">Any entity</option>
        <?php foreach ($entities as $entity): ?>
          <option value="<?= e((string) $entity['entity_type']) ?>" <?= $filters['entity'] === (string) $entity['entity_type'] ? 'selected' : '' ?>>
            <?= e((string) $entity['entity_type']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-brand" type="submit">Filter</button>
    <?php if ($filters['q'] !== '' || $filters['entity'] !== ''): ?>
      <a class="btn btn-light" href="<?= e(base_url('admin/audit')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <p class="text-muted"><?= e(number_format($total)) ?> entr<?= $total === 1 ? 'y' : 'ies' ?>.</p>

  <?php if ($entries === []): ?>
    <div class="empty-state">
      <h3>Nothing recorded</h3>
      <p>Actions appear here as soon as anything consequential happens on the platform.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr><th>When</th><th>Action</th><th>Entity</th><th>Who</th><th>IP</th><th class="wrap">Details</th></tr>
        </thead>
        <tbody>
          <?php foreach ($entries as $entry): ?>
            <tr>
              <td><?= e(dt((string) $entry['created_at'], 'j M Y H:i')) ?></td>
              <td class="mono"><?= e((string) $entry['action']) ?></td>
              <td>
                <?php if (!empty($entry['entity_type'])): ?>
                  <?= e((string) $entry['entity_type']) ?>
                  <span class="muted mono">#<?= e((string) $entry['entity_id']) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="wrap"><?= e((string) ($entry['user_email'] ?? 'system')) ?></td>
              <td class="mono"><?= e((string) ($entry['ip_address'] ?? '—')) ?></td>
              <td class="wrap muted" style="font-size:12.5px"><?= e($decode(isset($entry['safe_metadata_json']) ? (string) $entry['safe_metadata_json'] : null)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php $query = ['q' => $filters['q'], 'entity' => $filters['entity']]; require __DIR__ . '/../partials/pagination.php'; ?>
  <?php endif; ?>
</section>
