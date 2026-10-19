<?php
/**
 * Admin create / edit campaign form.
 *
 * @var array|null $campaign  null when creating
 * @var array      $statuses
 */
$isEdit = is_array($campaign);
$action = $isEdit ? base_url('admin/campaigns/' . (int) $campaign['id']) : base_url('admin/campaigns');
$image = $isEdit && !empty($campaign['image_path']) ? base_url((string) $campaign['image_path']) : null;
$val = static fn (string $key, string $default = ''): string => old($key, $isEdit ? (string) ($campaign[$key] ?? $default) : $default);
$dateVal = static fn (string $key): string => old($key, $isEdit && !empty($campaign[$key]) ? substr((string) $campaign[$key], 0, 10) : '');
$featured = old('featured', $isEdit ? (string) (int) ($campaign['featured'] ?? 0) : '0');
?>
<div class="dash-head">
  <div>
    <h1><?= $isEdit ? 'Edit campaign' : 'New campaign' ?></h1>
    <p class="sub"><?= $isEdit ? 'Changes appear on the public site as soon as you save.' : 'Appeals people can give to or start fundraisers for.' ?></p>
  </div>
  <a class="btn btn-light" href="<?= e(base_url('admin/campaigns')) ?>">Back to campaigns</a>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="panel" novalidate>
  <?= csrf_field() ?>

  <div class="field <?= error_for('title') !== '' ? 'is-invalid' : '' ?>">
    <label for="title">Title<span class="req">*</span></label>
    <input class="input" type="text" id="title" name="title" required minlength="5" maxlength="180"
           value="<?= $val('title') ?>" placeholder="School Places for 2026">
    <?php if (error_for('title') !== ''): ?><span class="field-error"><?= error_for('title') ?></span><?php endif; ?>
  </div>

  <div class="field <?= error_for('slug') !== '' ? 'is-invalid' : '' ?>">
    <label for="slug">Web address</label>
    <input class="input mono" type="text" id="slug" name="slug" maxlength="150" pattern="[a-z0-9]+(-[a-z0-9]+)*"
           value="<?= $val('slug') ?>" placeholder="school-places-2026">
    <span class="form-help">
      <?= $isEdit
          ? 'Leave as it is to keep the current link. Changing it breaks links that were already shared.'
          : 'Optional. Leave blank to create it from the title.' ?>
    </span>
    <?php if (error_for('slug') !== ''): ?><span class="field-error"><?= error_for('slug') ?></span><?php endif; ?>
  </div>

  <div class="field <?= error_for('description') !== '' ? 'is-invalid' : '' ?>">
    <label for="description">Description</label>
    <textarea class="textarea" id="description" name="description" rows="6" maxlength="5000"
              placeholder="What is this appeal for and what will the money do?"><?= $val('description') ?></textarea>
    <?php if (error_for('description') !== ''): ?><span class="field-error"><?= error_for('description') ?></span><?php endif; ?>
  </div>

  <div class="form-row">
    <div class="field <?= error_for('status') !== '' ? 'is-invalid' : '' ?>">
      <label for="status">Status<span class="req">*</span></label>
      <select class="select-field" id="status" name="status">
        <?php foreach ($statuses as $status): ?>
          <option value="<?= e($status) ?>" <?= $val('status', 'active') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="form-help">Only Active campaigns are shown to the public and can receive donations.</span>
      <?php if (error_for('status') !== ''): ?><span class="field-error"><?= error_for('status') ?></span><?php endif; ?>
    </div>
    <div class="field">
      <label>Featured</label>
      <label class="checkbox-row">
        <input type="checkbox" name="featured" value="1" <?= $featured === '1' ? 'checked' : '' ?>>
        <span>Show first on the home page and campaign list</span>
      </label>
    </div>
  </div>

  <div class="form-row">
    <div class="field <?= error_for('start_at') !== '' ? 'is-invalid' : '' ?>">
      <label for="start_at">Start date (optional)</label>
      <input class="input" type="date" id="start_at" name="start_at" value="<?= $dateVal('start_at') ?>">
      <?php if (error_for('start_at') !== ''): ?><span class="field-error"><?= error_for('start_at') ?></span><?php endif; ?>
    </div>
    <div class="field <?= error_for('end_at') !== '' ? 'is-invalid' : '' ?>">
      <label for="end_at">End date (optional)</label>
      <input class="input" type="date" id="end_at" name="end_at" value="<?= $dateVal('end_at') ?>">
      <span class="form-help">Leave blank for an open-ended campaign.</span>
      <?php if (error_for('end_at') !== ''): ?><span class="field-error"><?= error_for('end_at') ?></span><?php endif; ?>
    </div>
  </div>

  <div class="field <?= error_for('image') !== '' ? 'is-invalid' : '' ?>">
    <label for="image">Campaign image</label>
    <input class="input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    <span class="form-help">JPG, PNG or WebP, up to <?= e(app(\App\Services\UploadService::class)->humanBytes((int) config('security.uploads.max_bytes', 4194304))) ?>. Uploading a new image replaces the current one.</span>
    <?php if (error_for('image') !== ''): ?><span class="field-error"><?= error_for('image') ?></span><?php endif; ?>
  </div>

  <?php if ($image !== null): ?>
    <img src="<?= e($image) ?>" alt="Current campaign image" style="max-width:320px;border-radius:var(--radius);box-shadow:var(--shadow-sm)">
    <label class="checkbox-row" style="margin-top:10px">
      <input type="checkbox" name="remove_image" value="1">
      <span>Remove the current image</span>
    </label>
  <?php endif; ?>

  <div class="form-actions">
    <button class="btn btn-brand btn-lg" type="submit"><?= $isEdit ? 'Save changes' : 'Create campaign' ?></button>
    <a class="btn btn-light btn-lg" href="<?= e(base_url('admin/campaigns')) ?>">Cancel</a>
  </div>
</form>

<?php if ($isEdit): ?>
  <section class="panel">
    <h2>Delete this campaign</h2>
    <p class="muted">It is removed from the public site. Donations already made to it are kept in the records.</p>
    <form method="post" action="<?= e(base_url('admin/campaigns/' . (int) $campaign['id'] . '/delete')) ?>"
          data-confirm="Delete this campaign? It is removed from the site, but donations already made to it are kept in the records.">
      <?= csrf_field() ?>
      <button class="btn btn-light" type="submit" style="color:#a3231b">Delete campaign</button>
    </form>
  </section>
<?php endif; ?>
