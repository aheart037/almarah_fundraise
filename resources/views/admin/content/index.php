<?php
/**
 * Editable site copy and FAQs.
 *
 * @var array $blocks
 * @var array $faqs
 */

$fieldLabels = [
    'eyebrow' => 'Eyebrow', 'heading' => 'Heading', 'heading_em' => 'Heading emphasis (highlighted words)',
    'subheading' => 'Sub-heading', 'note' => 'Note', 'primary_cta_label' => 'Primary button label',
    'secondary_cta_label' => 'Secondary button label', 'image' => 'Background image path',
    'body' => 'Body', 'points' => 'Bullet points (separate with |)', 'email' => 'Email', 'phone' => 'Phone',
    'address' => 'Address', 'hours' => 'Opening hours', 'response' => 'Response time', 'updated' => 'Updated line',
    'title' => 'Title',
];
?>
<div class="dash-head">
  <div>
    <h1>Site content</h1>
    <p class="sub">Change the words on the public pages without touching the code.</p>
  </div>
</div>

<nav class="tabs">
  <?php foreach (array_keys($blocks) as $key): ?>
    <a href="#<?= e($key) ?>"><?= e(ucwords(str_replace('_', ' ', $key))) ?></a>
  <?php endforeach; ?>
  <a href="#faqs">FAQs</a>
</nav>

<?php foreach ($blocks as $key => $block): ?>
  <section class="panel" id="<?= e($key) ?>">
    <div class="panel-head">
      <h2><?= e(ucwords(str_replace('_', ' ', $key))) ?></h2>
      <?php if (in_array($key, ['privacy_policy', 'terms'], true)): ?>
        <span class="badge badge-purple">legal</span>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= e(base_url('admin/content/' . $key)) ?>">
      <?= csrf_field() ?>

      <?php foreach ($block as $field => $value): ?>
        <?php if ($field === 'items') { continue; } ?>
        <?php
          $label = $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));
          $isLong = in_array($field, ['body', 'subheading', 'note', 'points'], true) || mb_strlen((string) $value) > 160;
        ?>
        <div class="field">
          <label for="<?= e($key . '_' . $field) ?>"><?= e($label) ?></label>
          <?php if ($isLong): ?>
            <textarea class="textarea" id="<?= e($key . '_' . $field) ?>" name="<?= e($field) ?>" rows="<?= $field === 'body' ? 10 : 3 ?>"><?= e((string) $value) ?></textarea>
          <?php else: ?>
            <input class="input" type="text" id="<?= e($key . '_' . $field) ?>" name="<?= e($field) ?>" value="<?= e((string) $value) ?>">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($key === 'impact_numbers'): ?>
        <fieldset>
          <legend>Impact numbers</legend>
          <div id="impactRows">
            <?php foreach ((array) ($block['items'] ?? []) as $index => $item): ?>
              <div class="form-row" data-repeater-row>
                <div class="field">
                  <label>Value</label>
                  <input class="input" type="text" name="items[<?= e((string) $index) ?>][value]" value="<?= e((string) ($item['value'] ?? '')) ?>">
                </div>
                <div class="field">
                  <label>Label</label>
                  <input class="input" type="text" name="items[<?= e((string) $index) ?>][label]" value="<?= e((string) ($item['label'] ?? '')) ?>">
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="btn btn-light btn-sm" type="button" data-repeater-add="#impactRows">Add another number</button>
        </fieldset>
      <?php endif; ?>

      <button class="btn btn-brand" type="submit">Save <?= e(str_replace('_', ' ', $key)) ?></button>
    </form>
  </section>
<?php endforeach; ?>

<section class="panel" id="faqs">
  <h2>Frequently asked questions</h2>
  <p class="panel-sub"><?= e((string) count($faqs)) ?> question<?= count($faqs) === 1 ? '' : 's' ?>. Published ones appear on the public FAQ page, in sort order.</p>

  <form method="post" action="<?= e(base_url('admin/faqs')) ?>" class="panel tint mb-3">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="0">
    <div class="field">
      <label for="new_question">New question<span class="req">*</span></label>
      <input class="input" type="text" id="new_question" name="question" required minlength="8" maxlength="300"
             placeholder="How do I know my donation was received?">
    </div>
    <div class="field">
      <label for="new_answer">Answer<span class="req">*</span></label>
      <textarea class="textarea" id="new_answer" name="answer" rows="3" required minlength="10"></textarea>
    </div>
    <div class="form-row">
      <div class="field">
        <label for="new_sort">Sort order</label>
        <input class="input" type="number" id="new_sort" name="sort_order" value="<?= e((string) (count($faqs) + 1)) ?>">
      </div>
      <div class="field">
        <label for="new_status">Status</label>
        <select class="select-field" id="new_status" name="status">
          <option value="published">Published</option>
          <option value="draft">Draft</option>
        </select>
      </div>
    </div>
    <button class="btn btn-brand" type="submit">Add question</button>
  </form>

  <?php if ($faqs === []): ?>
    <p class="text-muted">No questions yet.</p>
  <?php else: ?>
    <?php foreach ($faqs as $faq): ?>
      <details class="panel" style="padding:0;margin-bottom:12px">
        <summary style="cursor:pointer;padding:14px 18px;font-weight:700;color:var(--ink-2)">
          <?= e((string) $faq['question']) ?>
          <span class="<?= e(status_badge_class((string) $faq['status'])) ?>" style="margin-left:8px"><?= e((string) $faq['status']) ?></span>
        </summary>
        <div style="padding:0 18px 18px">
          <form method="post" action="<?= e(base_url('admin/faqs')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((string) $faq['id']) ?>">
            <div class="field">
              <label for="q<?= e((string) $faq['id']) ?>">Question</label>
              <input class="input" type="text" id="q<?= e((string) $faq['id']) ?>" name="question" required value="<?= e((string) $faq['question']) ?>">
            </div>
            <div class="field">
              <label for="a<?= e((string) $faq['id']) ?>">Answer</label>
              <textarea class="textarea" id="a<?= e((string) $faq['id']) ?>" name="answer" rows="4" required><?= e((string) $faq['answer']) ?></textarea>
            </div>
            <div class="form-row">
              <div class="field">
                <label for="s<?= e((string) $faq['id']) ?>">Sort order</label>
                <input class="input" type="number" id="s<?= e((string) $faq['id']) ?>" name="sort_order" value="<?= e((string) $faq['sort_order']) ?>">
              </div>
              <div class="field">
                <label for="st<?= e((string) $faq['id']) ?>">Status</label>
                <select class="select-field" id="st<?= e((string) $faq['id']) ?>" name="status">
                  <option value="published" <?= (string) $faq['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                  <option value="draft" <?= (string) $faq['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                </select>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn btn-brand btn-sm" type="submit">Save</button>
            </div>
          </form>
          <form method="post" action="<?= e(base_url('admin/faqs/' . (string) $faq['id'] . '/delete')) ?>" data-confirm="Delete this question?">
            <?= csrf_field() ?>
            <button class="btn btn-light btn-sm" type="submit" style="color:#a3231b">Delete</button>
          </form>
        </div>
      </details>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
