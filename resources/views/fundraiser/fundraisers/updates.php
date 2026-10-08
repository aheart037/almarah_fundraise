<?php
/**
 * Post and manage updates for one fundraiser.
 *
 * @var array $fundraiser
 * @var array $updates
 */
$id = (int) $fundraiser['id'];
?>
<div class="dash-head">
  <div>
    <h1>Updates</h1>
    <p class="sub"><?= e((string) $fundraiser['title']) ?></p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-light" href="<?= e(base_url('dashboard/fundraisers/' . $id . '/edit')) ?>">Edit fundraiser</a>
    <?php if ((string) $fundraiser['status'] === 'published'): ?>
      <a class="btn btn-outline-brand" href="<?= e(base_url('fundraisers/' . (string) $fundraiser['slug'])) ?>">View public page</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Post an update</h2>
    <p class="panel-sub">Updates are emailed to everyone who has supported this fundraiser.</p>

    <form method="post" action="<?= e(base_url('dashboard/fundraisers/' . $id . '/updates')) ?>" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>

      <div class="field <?= error_for('title') !== '' ? 'is-invalid' : '' ?>">
        <label for="title">Update title<span class="req">*</span></label>
        <input class="input" type="text" id="title" name="title" required minlength="4" maxlength="150"
               value="<?= old('title') ?>" placeholder="We reached Rs 50,000!">
        <?php if (error_for('title') !== ''): ?><span class="field-error"><?= error_for('title') ?></span><?php endif; ?>
      </div>

      <div class="field <?= error_for('body') !== '' ? 'is-invalid' : '' ?>">
        <label for="body">What happened?<span class="req">*</span></label>
        <textarea class="textarea" id="body" name="body" rows="7" required minlength="20" maxlength="5000"
                  placeholder="Share the milestone, thank your donors, and tell them what comes next."><?= old('body') ?></textarea>
        <?php if (error_for('body') !== ''): ?><span class="field-error"><?= error_for('body') ?></span><?php endif; ?>
      </div>

      <div class="field <?= error_for('image') !== '' ? 'is-invalid' : '' ?>">
        <label for="image">Add a photo (optional)</label>
        <input class="input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
        <?php if (error_for('image') !== ''): ?><span class="field-error"><?= error_for('image') ?></span><?php endif; ?>
      </div>

      <button class="btn btn-brand btn-lg" type="submit" data-confirm="Publish this update and email your supporters?">Publish update</button>
    </form>
  </section>

  <section class="panel">
    <h2>Published updates</h2>
    <p class="panel-sub"><?= e((string) count($updates)) ?> update<?= count($updates) === 1 ? '' : 's' ?>.</p>

    <?php if ($updates === []): ?>
      <div class="empty-state">
        <h3>No updates yet</h3>
        <p>Donors give more when they can see progress. Post your first update when something happens — even a small thing.</p>
      </div>
    <?php else: ?>
      <ul class="timeline">
        <?php foreach ($updates as $update): ?>
          <li>
            <span class="when">
              <?= e(dt((string) ($update['published_at'] ?? $update['created_at']), 'j M Y')) ?>
              <?php if ((string) $update['status'] !== 'published'): ?>
                <span class="badge badge-grey" style="margin-left:6px"><?= e((string) $update['status']) ?></span>
              <?php endif; ?>
            </span>
            <div class="what"><?= e((string) $update['title']) ?></div>
            <div class="meta"><?= e(mb_substr((string) $update['body'], 0, 220)) ?><?= mb_strlen((string) $update['body']) > 220 ? '…' : '' ?></div>
            <?php if ((string) $update['status'] === 'published'): ?>
              <form method="post" class="inline-form mt-1"
                    action="<?= e(base_url('dashboard/fundraisers/' . $id . '/updates/' . (string) $update['id'] . '/delete')) ?>"
                    data-confirm="Delete this update? It will be removed from the public page. Donors who already received the email keep their copy.">
                <?= csrf_field() ?>
                <button class="btn btn-light btn-sm" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
