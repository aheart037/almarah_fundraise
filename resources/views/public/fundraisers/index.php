<?php
/**
 * Fundraiser directory with search, category filter and sorting.
 *
 * @var array $fundraisers
 * @var int $total
 * @var int $page
 * @var int $lastPage
 * @var array $filters
 * @var array $categories
 */
?>
<section class="page-banner">
  <div class="wrap">
    <span class="eyebrow">Support a Fundraiser</span>
    <h1>Find a cause close to your heart</h1>
    <p>Every fundraiser below has been reviewed by our team. Choose one, give what you can, and watch the progress bar move.</p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <form class="toolbar" method="get" action="<?= e(base_url('fundraisers')) ?>">
      <div class="search-field">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input class="input" type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Search fundraisers" aria-label="Search fundraisers">
      </div>

      <div class="select-field">
        <label class="sr-only" for="category">Cause</label>
        <select class="select-field" id="category" name="category" data-autosubmit>
          <option value="">All causes</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e((string) $category['id']) ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
              <?= e((string) $category['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="select-field">
        <label class="sr-only" for="state">Status</label>
        <select class="select-field" id="state" name="state" data-autosubmit>
          <option value="">Any status</option>
          <option value="active" <?= ($filters['state'] ?? '') === 'active' ? 'selected' : '' ?>>Still accepting</option>
          <option value="ending_soon" <?= ($filters['state'] ?? '') === 'ending_soon' ? 'selected' : '' ?>>Ending in 14 days</option>
          <option value="completed" <?= ($filters['state'] ?? '') === 'completed' ? 'selected' : '' ?>>Finished</option>
        </select>
      </div>

      <div class="select-field">
        <label class="sr-only" for="sort">Sort</label>
        <select class="select-field" id="sort" name="sort" data-autosubmit>
          <option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest first</option>
          <option value="most_raised" <?= ($filters['sort'] ?? '') === 'most_raised' ? 'selected' : '' ?>>Most raised</option>
          <option value="goal_percent" <?= ($filters['sort'] ?? '') === 'goal_percent' ? 'selected' : '' ?>>Closest to goal</option>
          <option value="ending_soon" <?= ($filters['sort'] ?? '') === 'ending_soon' ? 'selected' : '' ?>>Ending soonest</option>
        </select>
      </div>

      <button class="btn btn-brand" type="submit">Search</button>
      <?php if (($filters['q'] ?? '') !== '' || ($filters['category_id'] ?? '') !== '' || ($filters['state'] ?? '') !== ''): ?>
        <a class="btn btn-outline-brand" href="<?= e(base_url('fundraisers')) ?>">Clear</a>
      <?php endif; ?>
    </form>

    <p class="text-muted mt-2"><?= e(number_format($total)) ?> fundraiser<?= $total === 1 ? '' : 's' ?><?= ($filters['q'] ?? '') !== '' ? ' matching “' . e((string) $filters['q']) . '”' : '' ?>.</p>

    <?php if ($fundraisers === []): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <h3>No fundraisers match your search</h3>
        <p>Try a different cause, or clear the filters to see everything that is live right now.</p>
        <a class="btn btn-brand" href="<?= e(base_url('fundraisers')) ?>">Show all fundraisers</a>
      </div>
    <?php else: ?>
      <div class="card-grid cols-3 mt-3">
        <?php foreach ($fundraisers as $f): ?>
          <?php $showProgress = true; require __DIR__ . '/../../partials/fundraiser-card.php'; ?>
        <?php endforeach; ?>
      </div>

      <?php
      $query = ['q' => (string) ($filters['q'] ?? ''), 'category' => (string) ($filters['category_id'] ?? ''), 'state' => (string) ($filters['state'] ?? ''), 'sort' => (string) ($filters['sort'] ?? '')];
      require __DIR__ . '/../../partials/pagination.php';
      ?>
    <?php endif; ?>
  </div>
</section>

<section class="section tight cta-band">
  <div class="wrap">
    <h2>Not seeing the cause you care about?</h2>
    <p>Start your own fundraiser in about five minutes and rally your friends and family around it.</p>
    <div class="cta-actions">
      <a class="btn btn-gold btn-lg" href="<?= e(base_url('register')) ?>">Start Your Fundraiser</a>
    </div>
  </div>
</section>
