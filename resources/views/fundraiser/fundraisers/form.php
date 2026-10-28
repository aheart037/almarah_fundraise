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

<?php if (($categories ?? []) === []): ?>
  <div class="alert alert-warning" role="alert">
    <div>
      <strong>No causes are available yet.</strong>
      <p>A fundraiser must belong to a cause, and none have been set up on this site yet, so the form cannot be submitted. Please contact the Almarah Foundation team and ask them to add the causes (Admin → seed the fundraiser categories), then try again.</p>
    </div>
  </div>
<?php endif; ?>

<?php
// Quick-pick goal amounts, limited to what this site allows.
$goalFloorRs = (int) ($goalFloor / 100);
$goalCeilingRs = (int) ($goalCeiling / 100);
$goalPresets = array_values(array_filter(
    [25000, 50000, 100000, 250000, 500000],
    static fn (int $rs): bool => $rs >= $goalFloorRs && $rs <= $goalCeilingRs
));
$maxUpload = (int) config('security.uploads.max_bytes', 4194304);
$canSubmitForReview = !$isEdit || in_array($status, ['draft', 'changes_requested'], true);
$tick = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';
?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="fr-form" novalidate
      data-max-upload="<?= e((string) $maxUpload) ?>">
  <?= csrf_field() ?>
  <?php
  // The campaign picker was removed from this form. A link such as
  // /dashboard/fundraisers/create?campaign=3 (from a campaign page) still
  // carries its campaign through, quietly, on a brand-new fundraiser.
  if (!$isEdit && !empty($preselectCampaign) && ctype_digit((string) $preselectCampaign)): ?>
    <input type="hidden" name="campaign_id" value="<?= e((string) $preselectCampaign) ?>">
  <?php endif; ?>

  <div class="fr-layout">
    <div class="fr-main">

      <!-- 1 · STORY -->
      <section class="fr-card">
        <header class="fr-card-head">
          <span class="fr-step">1</span>
          <div>
            <h2>Tell your story</h2>
            <p>People give to people. Say who you are helping and why it matters to you.</p>
          </div>
        </header>

        <div class="field <?= error_for('title') !== '' ? 'is-invalid' : '' ?>">
          <label for="title">Fundraiser title<span class="req">*</span></label>
          <input class="input" type="text" id="title" name="title" required minlength="5" maxlength="150"
                 value="<?= old('title', $isEdit ? (string) $fundraiser['title'] : '') ?>"
                 placeholder="Ayesha's Birthday for 50 Ration Packs" autocomplete="off">
          <span class="form-help fr-help"><span>Short, clear and personal works best.</span><span class="fr-count" data-count-for="title" data-max="150"></span></span>
          <?php if (error_for('title') !== ''): ?><span class="field-error"><?= error_for('title') ?></span><?php endif; ?>
        </div>

        <div class="field <?= error_for('story') !== '' ? 'is-invalid' : '' ?>">
          <label for="story">Your story<span class="req">*</span></label>
          <textarea class="textarea" id="story" name="story" rows="9" required minlength="50" maxlength="20000"
                    placeholder="Why are you doing this, who are you helping, and what will the money do?"><?= old('story', $isEdit ? (string) $fundraiser['story'] : '') ?></textarea>
          <span class="form-help fr-help"><span>Write at least 50 characters. A few honest paragraphs beat a long essay.</span><span class="fr-count" data-count-for="story" data-min="50" data-max="20000"></span></span>
          <?php if (error_for('story') !== ''): ?><span class="field-error"><?= error_for('story') ?></span><?php endif; ?>
        </div>

        <div class="field <?= error_for('impact_statement') !== '' ? 'is-invalid' : '' ?>">
          <label for="impact_statement">What will the money do? <span class="fr-optional">Optional</span></label>
          <input class="input" type="text" id="impact_statement" name="impact_statement" maxlength="180"
                 value="<?= old('impact_statement', $isEdit ? (string) ($fundraiser['impact_statement'] ?? '') : '') ?>"
                 placeholder="Rs 5,000 feeds a child for a month" autocomplete="off">
          <span class="form-help fr-help"><span>One line shown on your card and page. It helps donors act.</span><span class="fr-count" data-count-for="impact_statement" data-max="180"></span></span>
          <?php if (error_for('impact_statement') !== ''): ?><span class="field-error"><?= error_for('impact_statement') ?></span><?php endif; ?>
        </div>
      </section>

      <!-- 2 · CAUSE -->
      <section class="fr-card">
        <header class="fr-card-head">
          <span class="fr-step">2</span>
          <div>
            <h2>Choose a cause</h2>
            <p>Your fundraiser is listed under this cause so the right supporters find it.</p>
          </div>
        </header>

        <div class="field <?= error_for('category_id') !== '' ? 'is-invalid' : '' ?>">
          <label for="category_id">Cause<span class="req">*</span></label>
          <select class="select-field fr-select" id="category_id" name="category_id" required>
            <option value="">Choose a cause</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= e((string) $category['id']) ?>"
                <?= (string) old('category_id', $isEdit ? (string) $fundraiser['category_id'] : '') === (string) $category['id'] ? 'selected' : '' ?>>
                <?= e((string) $category['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (error_for('category_id') !== ''): ?><span class="field-error"><?= error_for('category_id') ?></span><?php endif; ?>
          <?php if (error_for('campaign_id') !== ''): ?><span class="field-error"><?= error_for('campaign_id') ?></span><?php endif; ?>
        </div>
      </section>

      <!-- 3 · GOAL -->
      <section class="fr-card">
        <header class="fr-card-head">
          <span class="fr-step">3</span>
          <div>
            <h2>Set your goal</h2>
            <p>Pick an amount you can realistically reach, and how long you will run.</p>
          </div>
        </header>

        <div class="field <?= error_for('goal') !== '' ? 'is-invalid' : '' ?>">
          <label for="goal">Fundraising goal (PKR)<span class="req">*</span></label>
          <div class="input-group">
            <span class="prefix">Rs</span>
            <input class="input" type="number" inputmode="numeric" id="goal" name="goal" required step="100"
                   min="<?= e((string) $goalFloorRs) ?>" max="<?= e((string) $goalCeilingRs) ?>"
                   value="<?= old('goal', $isEdit ? number_format($goalMinor / 100, 0, '.', '') : '') ?>"
                   placeholder="50000">
          </div>
          <?php if ($goalPresets !== []): ?>
            <div class="fr-chips" role="group" aria-label="Quick goal amounts">
              <?php foreach ($goalPresets as $rs): ?>
                <button type="button" class="fr-chip" data-goal="<?= e((string) $rs) ?>">Rs <?= e(number_format($rs)) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <span class="form-help fr-help">
            <span>
              Between <?= e(money($goalFloor)) ?> and <?= e(money($goalCeiling)) ?>.
              <?php if ($isEdit && (string) $fundraiser['approval_status'] === 'approved'): ?>
                This fundraiser is live, so the goal cannot go below what is already raised.
              <?php endif; ?>
            </span>
            <span class="fr-goal-preview" id="goalPreview" aria-live="polite"></span>
          </span>
          <?php if (error_for('goal') !== ''): ?><span class="field-error"><?= error_for('goal') ?></span><?php endif; ?>
        </div>

        <div class="field <?= error_for('end_at') !== '' ? 'is-invalid' : '' ?>">
          <label for="end_at">End date <span class="fr-optional">Optional</span></label>
          <input class="input" type="date" id="end_at" name="end_at"
                 value="<?= old('end_at', $isEdit && !empty($fundraiser['end_at']) ? substr((string) $fundraiser['end_at'], 0, 10) : (string) ($defaultEnd ?? '')) ?>">
          <div class="fr-chips" role="group" aria-label="Quick end dates">
            <button type="button" class="fr-chip" data-days="30">30 days</button>
            <button type="button" class="fr-chip" data-days="60">60 days</button>
            <button type="button" class="fr-chip" data-days="90">90 days</button>
            <button type="button" class="fr-chip fr-chip-ghost" data-days="0">No end date</button>
          </div>
          <span class="form-help">Fundraisers with a deadline raise money faster. Leave blank to keep it open.</span>
          <?php if (error_for('end_at') !== ''): ?><span class="field-error"><?= error_for('end_at') ?></span><?php endif; ?>
        </div>
      </section>

      <!-- 4 · COVER -->
      <section class="fr-card">
        <header class="fr-card-head">
          <span class="fr-step">4</span>
          <div>
            <h2>Add a cover photo</h2>
            <p>A real, bright photo of the people you are helping earns the most trust.</p>
          </div>
        </header>

        <div class="field <?= error_for('cover_image') !== '' ? 'is-invalid' : '' ?>">
          <label class="fr-drop<?= $cover !== null ? ' has-image' : '' ?>" for="cover_image" id="coverDrop">
            <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp">
            <img class="fr-drop-preview" id="coverPreview" alt="Cover image preview"
                 <?= $cover !== null ? 'src="' . e($cover) . '"' : 'hidden' ?>>
            <span class="fr-drop-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 9"/></svg>
              <b>Click to choose a photo</b>
              <small>JPG, PNG or WebP, up to <?= e(app(\App\Services\UploadService::class)->humanBytes($maxUpload)) ?></small>
            </span>
            <span class="fr-drop-change"><?= $cover !== null ? 'Replace photo' : 'Change photo' ?></span>
          </label>
          <span class="field-error" id="coverError" hidden></span>
          <?php if ($cover !== null): ?><span class="form-help">Choosing a new photo replaces the current one.</span><?php endif; ?>
          <?php if (error_for('cover_image') !== ''): ?><span class="field-error"><?= error_for('cover_image') ?></span><?php endif; ?>
        </div>
      </section>

    </div>

    <aside class="fr-side">
      <div class="fr-card fr-actions-card">
        <h3><?= $isEdit ? 'Save your changes' : 'Ready when you are' ?></h3>
        <p class="fr-side-sub"><?= $isEdit ? 'Your changes are saved to this page.' : 'Save a draft now and finish later. Nothing goes live until our team approves it.' ?></p>

        <?php if ($canSubmitForReview): ?>
          <label class="fr-review">
            <input type="checkbox" name="submit_for_review" value="1" <?= old('submit_for_review', '') !== '' ? 'checked' : '' ?>>
            <span><b>Submit for review now</b><small>Our team usually replies within one working day.</small></span>
          </label>
        <?php endif; ?>

        <button class="btn btn-brand btn-lg btn-block" type="submit" id="frSubmit"><?= $isEdit ? 'Save changes' : 'Save draft' ?></button>
        <?php if ($isEdit): ?>
          <a class="btn btn-light btn-lg btn-block" href="<?= e(base_url('dashboard/fundraisers')) ?>">Done</a>
        <?php endif; ?>
      </div>

      <div class="fr-card fr-tips">
        <h3>Tips for a page that gets funded</h3>
        <ul>
          <li><?= $tick ?><span>Start with a clear, personal title.</span></li>
          <li><?= $tick ?><span>Explain exactly where the money goes.</span></li>
          <li><?= $tick ?><span>Use a real photo, not a stock image.</span></li>
          <li><?= $tick ?><span>Share it with ten people the day it goes live.</span></li>
        </ul>
      </div>
    </aside>
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
