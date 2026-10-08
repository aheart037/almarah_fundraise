<?php
/**
 * Admin review of a single fundraiser, with every moderation action available.
 *
 * @var array $fundraiser
 * @var array $donations
 * @var array $totals
 * @var array $updates
 * @var array|null $owner
 * @var array $auditTrail
 */

$id = (int) $fundraiser['id'];
$status = (string) $fundraiser['status'];
$approval = (string) $fundraiser['approval_status'];
$goal = (int) $fundraiser['goal_minor'];
$raised = (int) ($fundraiser['raised_minor'] ?? 0);
$percent = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : 0.0;
$ownerName = trim((string) ($owner['first_name'] ?? '') . ' ' . (string) ($owner['last_name'] ?? ''));
?>
<div class="dash-head">
  <div>
    <h1><?= e((string) $fundraiser['title']) ?></h1>
    <p class="sub">
      <span class="<?= e(status_badge_class($status)) ?>"><?= e(str_replace('_', ' ', $status)) ?></span>
      <span class="<?= e(status_badge_class($approval)) ?>"><?= e($approval) ?></span>
      <?php if (!empty($fundraiser['featured'])): ?><span class="badge badge-featured">featured</span><?php endif; ?>
    </p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-light" href="<?= e(base_url('admin/fundraisers')) ?>">Back to list</a>
    <?php if ($status === 'published'): ?>
      <a class="btn btn-outline-brand" href="<?= e(base_url('fundraisers/' . (string) $fundraiser['slug'])) ?>" target="_blank" rel="noopener">View public page</a>
    <?php endif; ?>
  </div>
</div>

<div class="stat-cards">
  <div class="stat-card brand">
    <div class="k">Confirmed</div>
    <div class="v"><?= e(money($raised)) ?></div>
    <div class="d"><?= e((string) (int) round($percent)) ?>% of <?= e(money_short($goal)) ?></div>
  </div>
  <div class="stat-card">
    <div class="k">Awaiting the bank</div>
    <div class="v"><?= e(money((int) ($totals['pending_minor'] ?? 0))) ?></div>
    <div class="d">Not counted toward the total</div>
  </div>
  <div class="stat-card">
    <div class="k">All attempts</div>
    <div class="v"><?= e((string) (int) ($totals['all_count'] ?? 0)) ?></div>
    <div class="d">Across every status</div>
  </div>
  <div class="stat-card">
    <div class="k">Donors</div>
    <div class="v"><?= e((string) (int) ($fundraiser['donor_count'] ?? 0)) ?></div>
    <div class="d">Unique completed gifts</div>
  </div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>The page</h2>
    <dl class="kv">
      <dt>Slug</dt><dd class="mono"><?= e((string) $fundraiser['slug']) ?></dd>
      <dt>Owner</dt>
      <dd>
        <?php if ($owner !== null): ?>
          <a href="<?= e(base_url('admin/users/' . (string) $owner['id'])) ?>"><?= e($ownerName !== '' ? $ownerName : (string) $owner['email']) ?></a>
          <div class="muted mono" style="font-size:12px"><?= e((string) $owner['email']) ?></div>
        <?php else: ?>Unknown<?php endif; ?>
      </dd>
      <dt>Cause</dt><dd><?= e((string) ($fundraiser['category_name'] ?? '—')) ?></dd>
      <dt>Campaign</dt><dd><?= e((string) ($fundraiser['campaign_title'] ?? '—')) ?></dd>
      <dt>Goal</dt><dd><?= e(money($goal)) ?></dd>
      <dt>Starts</dt><dd><?= e(dt((string) ($fundraiser['start_at'] ?? ''), 'j M Y')) ?></dd>
      <dt>Ends</dt><dd><?= e(dt(isset($fundraiser['end_at']) ? (string) $fundraiser['end_at'] : null, 'j M Y')) ?></dd>
      <dt>Created</dt><dd><?= e(dt((string) $fundraiser['created_at'], 'j M Y H:i')) ?> UTC</dd>
      <?php if (!empty($fundraiser['published_at'])): ?>
        <dt>Published</dt><dd><?= e(dt((string) $fundraiser['published_at'], 'j M Y H:i')) ?> UTC</dd>
      <?php endif; ?>
    </dl>

    <?php if (!empty($fundraiser['impact_statement'])): ?>
      <h3 class="mt-3" style="font-size:16px">Impact statement</h3>
      <p class="text-muted"><?= e((string) $fundraiser['impact_statement']) ?></p>
    <?php endif; ?>

    <h3 class="mt-3" style="font-size:16px">Story</h3>
    <div class="prose" style="max-height:340px;overflow:auto;background:var(--cream);padding:16px;border-radius:var(--radius)">
      <?= nl2br(e((string) $fundraiser['story'])) ?>
    </div>
  </section>

  <section class="panel">
    <h2>Moderation</h2>
    <p class="panel-sub">Every action is written to the audit log and, where relevant, emails the owner.</p>

    <div class="row-actions" style="display:flex;flex-wrap:wrap;gap:10px">
      <?php if ($approval !== 'approved' || $status !== 'published'): ?>
        <form method="post" action="<?= e(base_url('admin/fundraisers/' . $id . '/approve')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="note" value="Approved from the admin review screen.">
          <button class="btn btn-brand" type="submit">Approve &amp; publish</button>
        </form>
      <?php endif; ?>

      <?php if ($status === 'published'): ?>
        <form method="post" action="<?= e(base_url('admin/fundraisers/' . $id . '/unpublish')) ?>" data-confirm="Take this fundraiser off the public site?">
          <?= csrf_field() ?>
          <button class="btn btn-light" type="submit">Unpublish</button>
        </form>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('admin/fundraisers/' . $id . '/feature')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="featured" value="<?= !empty($fundraiser['featured']) ? '0' : '1' ?>">
        <button class="btn btn-light" type="submit"><?= !empty($fundraiser['featured']) ? 'Remove from featured' : 'Mark as featured' ?></button>
      </form>

      <?php if ($status !== 'archived'): ?>
        <form method="post" action="<?= e(base_url('admin/fundraisers/' . $id . '/archive')) ?>" data-confirm="Archive this fundraiser?">
          <?= csrf_field() ?>
          <button class="btn btn-light" type="submit">Archive</button>
        </form>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('admin/fundraisers/' . $id . '/delete')) ?>"
            data-confirm="Remove this fundraiser? The page is hidden but its donation history is retained for accounting.">
        <?= csrf_field() ?>
        <button class="btn btn-light" type="submit" style="color:#a3231b">Remove</button>
      </form>
    </div>

    <h3 class="mt-3" style="font-size:16px">Request changes</h3>
    <form method="post" action="<?= e(base_url('admin/fundraisers/' . $id . '/request-changes')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="changes_reason">What needs changing?<span class="req">*</span></label>
        <textarea class="textarea" id="changes_reason" name="reason" rows="3" required minlength="5" maxlength="1000"
                  placeholder="Please add a photo and confirm how the funds will be used."></textarea>
        <span class="form-help">This is emailed to the fundraiser owner.</span>
      </div>
      <button class="btn btn-outline-brand" type="submit">Send back for changes</button>
    </form>

    <h3 class="mt-3" style="font-size:16px">Reject</h3>
    <form method="post" action="<?= e(base_url('admin/fundraisers/' . $id . '/reject')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="reject_reason">Reason for rejection<span class="req">*</span></label>
        <textarea class="textarea" id="reject_reason" name="reason" rows="3" required minlength="5" maxlength="1000"
                  placeholder="We cannot publish fundraisers that raise funds for a third party."></textarea>
      </div>
      <button class="btn btn-outline-brand" type="submit" style="border-color:#a3231b;color:#a3231b">Reject fundraiser</button>
    </form>

    <h3 class="mt-3" style="font-size:16px">Pause</h3>
    <form method="post" action="<?= e(base_url('admin/fundraisers/' . $id . '/pause')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="pause_reason">Reason to pause<span class="req">*</span></label>
        <textarea class="textarea" id="pause_reason" name="reason" rows="2" required minlength="5" maxlength="1000"
                  placeholder="Paused while we verify the beneficiary details."></textarea>
        <span class="form-help">Pausing stops donations immediately and hides the page.</span>
      </div>
      <button class="btn btn-light" type="submit">Pause fundraiser</button>
    </form>
  </section>
</div>

<section class="panel">
  <h2>Donations</h2>
  <p class="panel-sub">The 15 most recent attempts on this fundraiser.</p>
  <?php if ($donations === []): ?>
    <p class="text-muted">No donations yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Reference</th><th>Date</th><th>Donor</th><th>Gateway</th><th>Status</th><th class="num">Amount</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($donations as $donation): ?>
            <tr>
              <td class="mono"><?= e((string) $donation['public_reference']) ?></td>
              <td><?= e(dt((string) $donation['created_at'], 'j M Y H:i')) ?></td>
              <td class="wrap"><?= e(!empty($donation['anonymous']) ? 'Anonymous' : (string) ($donation['donor_name'] ?? '—')) ?></td>
              <td><?= e(ucfirst((string) ($donation['gateway_code'] ?? '—'))) ?></td>
              <td><span class="<?= e(status_badge_class((string) $donation['status'])) ?>"><?= e((string) $donation['status']) ?></span></td>
              <td class="num"><?= e(money((int) $donation['amount_minor'])) ?></td>
              <td><a class="btn btn-light btn-sm" href="<?= e(base_url('admin/donations/' . (string) $donation['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if ($updates !== []): ?>
  <section class="panel">
    <h2>Updates</h2>
    <ul class="timeline">
      <?php foreach ($updates as $update): ?>
        <li>
          <span class="when"><?= e(dt((string) ($update['published_at'] ?? $update['created_at']), 'j M Y')) ?></span>
          <div class="what"><?= e((string) $update['title']) ?> <span class="badge badge-grey"><?= e((string) $update['status']) ?></span></div>
          <div class="meta"><?= e(mb_substr((string) $update['body'], 0, 200)) ?></div>
          <?php if ((string) $update['status'] === 'published'): ?>
            <form method="post" class="inline-form mt-1" action="<?= e(base_url('admin/fundraisers/' . $id . '/updates/' . (string) $update['id'] . '/hide')) ?>"
                  data-confirm="Hide this update from the public page?">
              <?= csrf_field() ?>
              <button class="btn btn-light btn-sm" type="submit">Hide</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section class="panel">
  <h2>Audit trail</h2>
  <p class="panel-sub">Everything that has happened to this fundraiser.</p>
  <?php if ($auditTrail === []): ?>
    <p class="text-muted">No entries recorded.</p>
  <?php else: ?>
    <ul class="timeline">
      <?php foreach ($auditTrail as $entry): ?>
        <li>
          <span class="when"><?= e(dt((string) $entry['created_at'], 'j M Y H:i')) ?> UTC</span>
          <div class="what"><?= e((string) $entry['action']) ?></div>
          <div class="meta"><?= e((string) ($entry['user_email'] ?? ($entry['user_id'] ?? 'system'))) ?> &middot; <?= e((string) ($entry['ip_address'] ?? '')) ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
