<?php
/**
 * Admin → Fonts.
 *
 * @var array  $current   role => [font, weight, size]
 * @var array  $catalog   font key => [label, group, css, min, max]
 * @var string $fontFaces @font-face rules so the preview can show every font
 * @var int    $sizeMin
 * @var int    $sizeMax
 */
$weightNames = [100 => 'Thin', 200 => 'Extra light', 300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'Semi-bold', 700 => 'Bold', 800 => 'Extra bold', 900 => 'Black'];

$roles = [
    'heading' => ['Headings', 'The big titles on every page (the main title and section titles).', 'Every child deserves a safe home'],
    'sub'     => ['Sub-headings', 'Smaller titles: card titles, panel titles and sub-sections.', 'How your donation helps'],
    'body'    => ['Paragraph text', 'Normal text: descriptions, stories and explanations.', 'Your gift provides meals, schooling and a safe place to sleep for orphaned children across Pakistan.'],
];

// Which font the weight list should be built for.
$fontForWeights = static function (string $role) use ($current): string {
    $font = $current[$role]['font'];
    return ($role === 'sub' && $font === '') ? $current['heading']['font'] : $font;
};

$groups = [];
foreach ($catalog as $key => $font) {
    $groups[$font['group']][$key] = $font['label'];
}
?>
<style><?= $fontFaces ?>
.ty-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:24px;align-items:start}
.ty-role{padding:18px 20px;border:1px solid var(--line);border-radius:var(--radius-lg);margin-bottom:16px;background:#fff}
.ty-role h3{margin:0 0 4px;font-size:1.05rem}
.ty-role .sub{margin:0 0 14px;color:var(--muted);font-size:.88rem}
.ty-fields{display:grid;grid-template-columns:2fr 1fr;gap:12px}
.ty-size{display:flex;align-items:center;gap:12px;margin-top:12px}
.ty-size input[type=range]{flex:1;accent-color:var(--brand)}
.ty-size input[type=number]{width:88px}
.ty-preview{position:sticky;top:96px}
.ty-preview .sample{padding:22px;border:1px solid var(--line);border-radius:var(--radius-lg);background:#fff}
.ty-preview .sample > *{margin-top:0}
@media (max-width:980px){.ty-grid{grid-template-columns:1fr}.ty-preview{position:static}}
</style>

<div class="dash-head">
  <div>
    <h1>Fonts</h1>
    <p class="sub">Choose the font, weight and size for headings, sub-headings and paragraph text across the public website.</p>
  </div>
</div>

<div class="ty-grid">
  <form method="post" action="<?= e(base_url('admin/typography')) ?>" id="typographyForm"
        data-fonts="<?= e((string) json_encode($catalog, JSON_UNESCAPED_UNICODE)) ?>"
        data-weight-names="<?= e((string) json_encode($weightNames)) ?>">
    <?= csrf_field() ?>

    <?php foreach ($roles as $role => [$title, $help, $sample]): ?>
      <?php
        $font = $current[$role]['font'];
        $wFont = $fontForWeights($role);
        $min = (int) ($catalog[$wFont]['min'] ?? 100);
        $max = (int) ($catalog[$wFont]['max'] ?? 900);
      ?>
      <section class="ty-role" data-role="<?= e($role) ?>">
        <h3><?= e($title) ?></h3>
        <p class="sub"><?= e($help) ?></p>

        <div class="ty-fields">
          <div class="field">
            <label for="<?= e($role) ?>_font">Font</label>
            <select class="select-field" id="<?= e($role) ?>_font" name="<?= e($role) ?>_font" data-font-select>
              <?php if ($role === 'sub'): ?>
                <option value="" <?= $font === '' ? 'selected' : '' ?>>Same as headings</option>
              <?php endif; ?>
              <?php foreach ($groups as $group => $items): ?>
                <optgroup label="<?= e($group) ?>">
                  <?php foreach ($items as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $font === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label for="<?= e($role) ?>_weight">Weight</label>
            <select class="select-field" id="<?= e($role) ?>_weight" name="<?= e($role) ?>_weight" data-weight-select>
              <option value="0" <?= $current[$role]['weight'] === 0 ? 'selected' : '' ?>>Site default</option>
              <?php for ($w = 100; $w <= 900; $w += 100): ?>
                <?php if ($w >= $min && $w <= $max): ?>
                  <option value="<?= $w ?>" <?= $current[$role]['weight'] === $w ? 'selected' : '' ?>><?= e($weightNames[$w] . ' (' . $w . ')') ?></option>
                <?php endif; ?>
              <?php endfor; ?>
            </select>
          </div>
        </div>

        <div class="ty-size">
          <label for="<?= e($role) ?>_size" style="margin:0;min-width:64px">Size</label>
          <input type="range" min="<?= (int) $sizeMin ?>" max="<?= (int) $sizeMax ?>" step="5" value="<?= (int) $current[$role]['size'] ?>" data-size-range aria-label="<?= e($title) ?> size">
          <input class="input" type="number" id="<?= e($role) ?>_size" name="<?= e($role) ?>_size" min="<?= (int) $sizeMin ?>" max="<?= (int) $sizeMax ?>" step="5" value="<?= (int) $current[$role]['size'] ?>" data-size-number>
          <span class="muted">%</span>
        </div>
      </section>
    <?php endforeach; ?>

    <p class="form-help" style="margin:-4px 0 16px">
      Size is a percentage of the normal size (100% = unchanged, from <?= (int) $sizeMin ?>% to <?= (int) $sizeMax ?>%).
      Fonts are served from your own website, so pages stay fast and nothing is loaded from third parties.
      The admin area itself keeps its standard font.
    </p>

    <div class="form-actions">
      <button class="btn btn-brand btn-lg" type="submit">Save fonts</button>
    </div>
  </form>

  <aside class="ty-preview">
    <div class="panel">
      <h2 style="margin-top:0">Preview</h2>
      <div class="sample">
        <h2 data-preview="heading" style="font-size:32px;margin-bottom:12px"><?= e($roles['heading'][2]) ?></h2>
        <h3 data-preview="sub" style="font-size:22px;margin-bottom:10px"><?= e($roles['sub'][2]) ?></h3>
        <p data-preview="body" style="font-size:16px;margin-bottom:0"><?= e($roles['body'][2]) ?></p>
      </div>
      <p class="muted" style="font-size:13px;margin:12px 0 0">The preview updates as you change the options. Nothing goes live until you press Save.</p>
    </div>

    <form method="post" action="<?= e(base_url('admin/typography/reset')) ?>" data-confirm="Go back to the original fonts and sizes?" style="margin-top:14px">
      <?= csrf_field() ?>
      <button class="btn btn-light btn-sm" type="submit">Reset to original fonts</button>
    </form>
  </aside>
</div>

<script src="<?= e(asset('assets/js/admin-typography.js')) ?>" defer></script>
