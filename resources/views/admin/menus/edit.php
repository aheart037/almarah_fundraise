<?php
/**
 * Admin → Menus → edit one menu and its links.
 *
 * @var array $menu
 * @var array $locations
 * @var array $pages     group => [path => label]
 * @var int   $maxItems
 */
$id = (int) $menu['id'];
$items = (array) $menu['items'];

$row = static function (string $index, array $item, array $pages): void {
    $type = ($item['type'] ?? 'page') === 'custom' ? 'custom' : 'page';
    $target = (string) ($item['target'] ?? '');
    ?>
    <div class="mn-row" data-row>
      <input type="hidden" name="items[<?= e($index) ?>][order]" value="<?= e($index === '__INDEX__' ? '0' : (string) ((int) $index + 1)) ?>" data-order>
      <div class="mn-move">
        <button type="button" class="btn btn-light btn-sm" data-move="up" aria-label="Move up">&uarr;</button>
        <button type="button" class="btn btn-light btn-sm" data-move="down" aria-label="Move down">&darr;</button>
      </div>
      <div class="field">
        <label>Label</label>
        <input class="input" type="text" name="items[<?= e($index) ?>][label]" maxlength="60" value="<?= e((string) ($item['label'] ?? '')) ?>" placeholder="Text people click">
      </div>
      <div class="field">
        <label>Link to</label>
        <select class="select-field" name="items[<?= e($index) ?>][type]" data-type>
          <option value="page" <?= $type === 'page' ? 'selected' : '' ?>>A page of this site</option>
          <option value="custom" <?= $type === 'custom' ? 'selected' : '' ?>>Another web address</option>
        </select>
      </div>
      <div class="field" data-target-page <?= $type === 'custom' ? 'hidden' : '' ?>>
        <label>Page</label>
        <select class="select-field" name="items[<?= e($index) ?>][page]">
          <option value="">Choose a page…</option>
          <?php foreach ($pages as $group => $choices): ?>
            <optgroup label="<?= e($group) ?>">
              <?php foreach ($choices as $path => $label): ?>
                <option value="<?= e((string) $path) ?>" <?= ($type === 'page' && $target === (string) $path) ? 'selected' : '' ?>><?= e((string) $label) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" data-target-custom <?= $type === 'page' ? 'hidden' : '' ?>>
        <label>Web address</label>
        <input class="input mono" type="text" name="items[<?= e($index) ?>][url]" maxlength="500" value="<?= $type === 'custom' ? e($target) : '' ?>" placeholder="https://…">
      </div>
      <div class="mn-opts">
        <label class="checkbox-row"><input type="checkbox" name="items[<?= e($index) ?>][newtab]" value="1" <?= !empty($item['newtab']) ? 'checked' : '' ?>><span>New tab</span></label>
        <label class="checkbox-row mn-remove"><input type="checkbox" name="items[<?= e($index) ?>][remove]" value="1" data-remove><span>Remove</span></label>
      </div>
    </div>
    <?php
};
?>
<style>
.mn-row{display:grid;grid-template-columns:auto minmax(120px,1.2fr) minmax(150px,1fr) minmax(180px,1.5fr) auto;gap:10px 12px;align-items:end;padding:14px;border:1px solid var(--line);border-radius:var(--radius);background:#fff;margin-bottom:10px}
.mn-row .field{margin:0}
.mn-row[hidden],.mn-row .field[hidden]{display:none}
.mn-row.is-removed{opacity:.45;background:#fdf6f6}
.mn-move{display:flex;flex-direction:column;gap:4px}
.mn-opts{display:flex;flex-direction:column;gap:2px;font-size:.85rem}
.mn-remove span{color:#a3231b}
@media (max-width:900px){.mn-row{grid-template-columns:1fr 1fr}.mn-move{flex-direction:row}}
</style>

<div class="dash-head">
  <div>
    <h1>Edit menu</h1>
    <p class="sub">Add, remove and re-order the links. Changes show on the website as soon as you save.</p>
  </div>
  <a class="btn btn-light" href="<?= e(base_url('admin/menus')) ?>">Back to menus</a>
</div>

<form method="post" action="<?= e(base_url('admin/menus/' . $id)) ?>" id="menuForm" data-max-items="<?= (int) $maxItems ?>">
  <?= csrf_field() ?>

  <section class="panel">
    <div class="form-row">
      <div class="field">
        <label for="m_name">Menu name<span class="req">*</span></label>
        <input class="input" type="text" id="m_name" name="name" maxlength="60" required value="<?= e((string) $menu['name']) ?>">
      </div>
      <div class="field">
        <label for="m_location">Show it in</label>
        <select class="select-field" id="m_location" name="location" data-location>
          <option value="">Nowhere yet (draft)</option>
          <?php foreach ($locations as $key => $loc): ?>
            <option value="<?= e($key) ?>" data-heading="<?= $loc['heading'] ? '1' : '0' ?>" <?= $menu['location'] === $key ? 'selected' : '' ?>><?= e($loc['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="form-help">A place shows one menu. Picking a place another menu uses moves it to this one.</span>
      </div>
    </div>
    <div class="field" data-heading-field>
      <label for="m_title">Footer column heading</label>
      <input class="input" type="text" id="m_title" name="title" maxlength="60" value="<?= e((string) ($menu['title'] ?? '')) ?>" placeholder="<?= e((string) $menu['name']) ?>">
      <span class="form-help">Shown above the links in the footer. Leave blank to use the menu name.</span>
    </div>
  </section>

  <section class="panel">
    <h2>Links</h2>
    <p class="muted" style="margin-top:0">Use the arrows to re-order. Tick <b>Remove</b> to take a link out, then save. A menu with no links makes the site use its standard links.</p>

    <div id="menuRows">
      <?php foreach ($items as $i => $item): ?>
        <?php $row((string) $i, (array) $item, $pages); ?>
      <?php endforeach; ?>
      <?php for ($extra = count($items); $extra < count($items) + 2; $extra++): ?>
        <?php $row((string) $extra, [], $pages); ?>
      <?php endfor; ?>
    </div>

    <template id="menuRowTemplate"><?php $row('__INDEX__', [], $pages); ?></template>

    <button class="btn btn-light btn-sm" type="button" data-add-row>+ Add another link</button>
  </section>

  <div class="form-actions">
    <button class="btn btn-brand btn-lg" type="submit">Save menu</button>
    <a class="btn btn-light btn-lg" href="<?= e(base_url('admin/menus')) ?>">Cancel</a>
  </div>
</form>

<script src="<?= e(asset('assets/js/admin-menus.js')) ?>" defer></script>
