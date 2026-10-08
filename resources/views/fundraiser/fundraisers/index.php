<?php
/**
 * Owner's fundraiser list.
 *
 * @var array $fundraisers
 */
?>
<div class="dash-head">
  <div>
    <h1>My fundraisers</h1>
    <p class="sub">Everything you have created, in every state.</p>
  </div>
  <a class="btn btn-brand" href="<?= e(base_url('dashboard/fundraisers/create')) ?>">Start a fundraiser</a>
</div>

<?php if ($fundraisers === []): ?>
  <div class="panel">
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
      <h3>You have not started a fundraiser yet</h3>
      <p>Tell your story, set a goal and share it. Our team reviews every page before it goes live.</p>
      <a class="btn btn-brand" href="<?= e(base_url('dashboard/fundraisers/create')) ?>">Start your first fundraiser</a>
    </div>
  </div>
<?php else: ?>
  <section class="panel">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Fundraiser</th>
            <th>Status</th>
            <th class="num">Raised</th>
            <th class="num">Goal</th>
            <th class="num">Donors</th>
            <th>Ends</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fundraisers as $row): ?>
            <?php
              $id = (int) $row['id'];
              $status = (string) $row['status'];
              $raised = (int) $row['raised_minor'];
              $goal = (int) $row['goal_minor'];
              $percent = $goal > 0 ? min(100.0, round($raised / $goal * 100)) : 0;
            ?>
            <tr>
              <td class="wrap">
                <b><?= e((string) $row['title']) ?></b>
                <div class="muted mono" style="font-size:12px">/fundraisers/<?= e((string) $row['slug']) ?></div>
                <?php if (!empty($row['category_name'])): ?>
                  <span class="badge badge-grey" style="margin-top:6px"><?= e((string) $row['category_name']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="<?= e(status_badge_class($status)) ?>"><?= e(str_replace('_', ' ', $status)) ?></span>
                <?php if (!empty($row['featured'])): ?>
                  <span class="badge badge-featured" style="margin-top:6px">Featured</span>
                <?php endif; ?>
              </td>
              <td class="num"><?= e(money_short($raised)) ?></td>
              <td class="num"><?= e(money_short($goal)) ?></td>
              <td class="num">
                <?= e((string) (int) $row['donor_count']) ?>
                <?php if ((int) $row['pending_count'] > 0): ?>
                  <div class="muted" style="font-size:12px"><?= e((string) (int) $row['pending_count']) ?> pending</div>
                <?php endif; ?>
              </td>
              <td><?= e(dt(isset($row['end_at']) ? (string) $row['end_at'] : null, 'j M Y')) ?></td>
              <td>
                <div class="row-actions">
                  <?php if ($status === 'published'): ?>
                    <a class="btn btn-light btn-sm" href="<?= e(base_url('fundraisers/' . (string) $row['slug'])) ?>">View</a>
                  <?php endif; ?>
                  <a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('dashboard/fundraisers/' . $id . '/edit')) ?>">Edit</a>
                  <a class="btn btn-light btn-sm" href="<?= e(base_url('dashboard/fundraisers/' . $id . '/donations')) ?>">Donations</a>
                  <a class="btn btn-light btn-sm" href="<?= e(base_url('dashboard/fundraisers/' . $id . '/updates')) ?>">Updates</a>
                  <?php if (in_array($status, ['draft', 'changes_requested'], true)): ?>
                    <form method="post" action="<?= e(base_url('dashboard/fundraisers/' . $id . '/submit')) ?>" data-confirm="Submit this fundraiser for review?">
                      <?= csrf_field() ?>
                      <button class="btn btn-brand btn-sm" type="submit">Submit</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($status === 'published'): ?>
                    <form method="post" action="<?= e(base_url('dashboard/fundraisers/' . $id . '/pause')) ?>" data-confirm="Pause this fundraiser? It will stop accepting donations.">
                      <?= csrf_field() ?>
                      <button class="btn btn-light btn-sm" type="submit">Pause</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="alert alert-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
    <p>Need a fundraiser taken down or transferred? <a href="<?= e(base_url('support')) ?>">Contact our team</a> and we will help.</p>
  </div>
<?php endif; ?>
