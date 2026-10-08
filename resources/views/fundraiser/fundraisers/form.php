<?php
/**
 * Create / edit a fundraiser.
 *
 * @var array|null $fundraiser
 * @var array $categories
 * @var array $campaigns
 * @var int $goalFloor      minor units
 * @var int $goalCeiling    minor units
 */

$isEdit = $fundraiser !== null;
$id = $isEdit ? (int) $fundraiser['id'] : 0;
$status = $isEdit ? (string) $fundraiser['status'] : 'draft';
$goalMinor = $isEdit ? (int) $fundraiser['goal_minor'] : (int) old('goal', '0');
$action = $isEdit ? base_url('dashboard/fundraisers/' . $id) : base_url('dashboard/fundraisers');
$cover = $isEdit && !empty($fundraiser['cover_image_path']) ? base_url((string) $fundraiser['cover_image_path']) : null;
?>
<div class="dash-head">
  <div>
    <h1><?= $isEdit ? 'Edit your fundraiser' : 'Start a fundraiser' ?></h1>
    <p class="sub">
      <?php if ($isEdit): ?>
        Status: <span class="<?= e(status_badge_class($status)) ?>"><?= e(str_replace('_', ' ', $status)) ?></span>
        <?php if (!empty($fundraiser['rejection_reason'])): ?>
          <br><span class="text-muted">Our note: <?= e((string) $fundraiser['rejection_reason']) ?></span>
        <?php endif; ?>
      <?php else: ?>
        Save it as a draft and submit when you are ready — our team reviews every page.
      <?php endif; ?>
    </p>
  </div>
  <div class="sr-actions">
    <a class="btn btn-light" href="<?= e(base_url('dashboard/fundraisers')) ?>">Back to my fundraisers</a>
    <?php if ($isEdit && $status === 'published'): ?>
      <a class="btn btn-outline-brand" href="<?= e(base_url('fundraisers/' . (string) $fundraiser['slug'])) ?>">View public page</a>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="panel" novalidate>
  <?= csrf_field() ?>

  <fieldset>
    <legend>Your story</legend>

    <div class="field <?= error_for('title') !== '' ? 'is-invalid' : '' ?>">
      <label for="title">Fundraiser title<span class="req">*</span></label>
      <input class="input" type="text" id="title" name="title" required minlength="5" maxlength="150"
             value="<?= old('title', $isEdit ? (string) $fundraiser['title'] : '') ?>"
             placeholder="Ayesha's Birthday for 50 Ration Packs">
      <span class="form-help">A clear, personal title works best. 5–150 characters.</span>
      <?php if (error_for('title') !== ''): ?><span class="field-error"><?= error_for('title') ?></span><?php endif; ?>
    </div>

    <div class="field <?= error_for('story') !== '' ? 'is-invalid' : '' ?>">
      <label for="story">Your story<span class="req">*</span></label>
      <textarea class="textarea" id="story" name="story" rows="10" required minlength="50" maxlength="20000"
                placeholder="Why are you doing this, who are you helping, and what will the money do?"><?= old('story', $isEdit ? (string) $fundraiser['story'] : '') ?></textarea>
      <span class="form-help">At least 50 characters. Share the reason behind your fundraiser — donors give to people.</span>
      <?php if (error_for('story') !== ''): ?><span class="field-error"><?= error_for('story') ?></span><?php endif; ?>
    </div>

    <div class="field">
      <label for="impact_statement">What your supporters are funding</label>
      <input class="input" type="text" id="impact_statement" name="impact_statement" maxlength="500"
             value="<?= old('impact_statement', $isEdit ? (string) ($fundraiser['impact_statement'] ?? '') : '') ?>"
             placeholder="Rs 5,000 feeds a child for a month">
      <span class="form-help">One short line shown on your card and page. Optional but it helps a lot.</span>
    </div>
  </fieldset>

  <fieldset>
    <legend>Cause and campaign</legend>

    <div class="form-row">
      <div class="field <?= error_for('category_id') !== '' ? 'is-invalid' : '' ?>">
        <label for="category_id">Cause<span class="req">*</span></label>
        <select class="select-field" id="category_id" name="category_id" required>
          <option value="">Choose a cause</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e((string) $category['id']) ?>"
              <?= (string) old('category_id', $isEdit ? (string) $fundraiser['category_id'] : '') === (string) $category['id'] ? 'selected' : '' ?>>
              <?= e((string) $category['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (error_for('category_id') !== ''): ?><span class="field-error"><?= error_for('category_id') ?></span><?php endif; ?>
      </div>

      <div class="field">
        <label for="campaign_id">Campaign (optional)</label>
        <select class="select-field" id="campaign_id" name="campaign_id">
          <option value="">No campaign</option>
          <?php foreach ($campaigns as $campaign): ?>
            <option value="<?= e((string) $campaign['id']) ?>"
              <?= (string) old('campaign_id', $isEdit ? (string) ($fundraiser['campaign_id'] ?? '') : (string) $preselectCampaign) === (string) $campaign['id'] ? 'selected' : '' ?>>
              <?= e((string) $campaign['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="form-help">Linking to a campaign puts your page in front of people browsing that appeal.</span>
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Goal and dates</legend>

    <div class="form-row">
      <div class="field <?= error_for('goal') !== '' ? 'is-invalid' : '' ?>">
        <label for="goal">Fundraising goal (PKR)<span class="req">*</span></label>
        <div class="input-group">
          <span class="prefix">Rs</span>
          <input class="input" type="number" id="goal" name="goal" required step="100"
                 min="<?= e((string) (int) ($goalFloor / 100)) ?>" max="<?= e((string) (int) ($goalCeiling / 100)) ?>"
                 value="<?= old('goal', $isEdit ? number_format($goalMinor / 100, 0, '.', '') : '') ?>">
        </div>
        <span class="form-help">
          Between <?= e(money($goalFloor)) ?> and <?= e(money($goalCeiling)) ?>.
          <?php if ($isEdit && (string) $fundraiser['approval_status'] === 'approved'): ?>
            Because this fundraiser is live, you cannot lower the goal below the amount already raised.
          <?php endif; ?>
        </span>
        <?php if (error_for('goal') !== ''): ?><span class="field-error"><?= error_for('goal') ?></span><?php endif; ?>
      </div>

      <div class="field">
        <label for="end_at">End date (optional)</label>
        <input class="input" type="date" id="end_at" name="end_at"
               value="<?= old('end_at', $isEdit && !empty($fundraiser['end_at']) ? substr((string) $fundraiser['end_at'], 0, 10) : (string) ($defaultEnd ?? '')) ?>">
        <span class="form-help">Leave blank for an open-ended fundraiser.</span>
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Cover image</legend>

    <div class="field <?= error_for('cover_image') !== '' ? 'is-invalid' : '' ?>">
      <label for="cover_image">Upload a cover photo</label>
      <input class="input" type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp">
      <span class="form-help">JPG, PNG or WebP, up to <?= e(app(\App\Services\UploadService::class)->humanBytes((int) config('security.uploads.max_bytes', 4194304))) ?>. A photo of the people you are helping works best.</span>
      <?php if (error_for('cover_image') !== ''): ?><span class="field-error"><?= error_for('cover_image') ?></span><?php endif; ?>
    </div>

    <?php if ($cover !== null): ?>
      <img src="<?= e($cover) ?>" alt="Current cover image" style="max-width:320px;border-radius:var(--radius);box-shadow:var(--shadow-sm)">
      <p class="form-help">Uploading a new image replaces this one.</p>
    <?php endif; ?>
  </fieldset>

  <div class="form-actions">
    <button class="btn btn-brand btn-lg" type="submit"><?= $isEdit ? 'Save changes' : 'Save draft' ?></button>

    <?php if (!$isEdit || in_array($status, ['draft', 'changes_requested'], true)): ?>
      <label class="checkbox-row" style="margin:0">
        <input type="checkbox" name="submit_for_review" value="1">
        <span>Submit for review now</span>
      </label>
    <?php endif; ?>

    <?php if ($isEdit): ?>
      <a class="btn btn-light btn-lg" href="<?= e(base_url('dashboard/fundraisers')) ?>">Done</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($isEdit): ?>
  <section class="panel">
    <h2>What happens next</h2>
    <ul class="checkline" style="display:grid;gap:10px;margin-top:12px">
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg>
        <span><b>Draft</b> — only you can see it. Edit as much as you like.</span>
      </li>
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg>
        <span><b>In review</b> — our team checks it, usually within one working day.</span>
      </li>
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;color:var(--brand)"><path d="M20 6L9 17l-5-5"/></svg>
        <span><b>Live</b> — you can start sharing. Donations are verified with the bank before they count.</span>
      </li>
    </ul>
  </section>
<?php endif; ?>
