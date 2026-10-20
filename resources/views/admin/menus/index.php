<?php
/**
 * Admin → Menus: list and create.
 *
 * @var array $menus
 * @var array $locations
 * @var int   $maxMenus
 */
?>
<div class="dash-head">
  <div>
    <h1>Menus</h1>
    <p class="sub">Build the links shown in the website header and footer. Where no menu is assigned, the site keeps its standard links.</p>
  </div>
</div>

<div class="grid-2">
  <section class="panel">
    <h2>Your menus</h2>
    <?php if ($menus === []): ?>
      <div class="empty-state">
        <h3>No menus yet</h3>
        <p>Create your first menu on the right, then add pages to it.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Menu</th><th>Shown in</th><th class="num">Links</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($menus as $menu): ?>
              <?php $id = (int) $menu['id']; $where = (string) $menu['location']; ?>
              <tr>
                <td class="wrap"><b><a href="<?= e(base_url('admin/menus/' . $id . '/edit')) ?>"><?= e((string) $menu['name']) ?></a></b></td>
                <td>
                  <?php if ($where !== '' && isset($locations[$where])): ?>
                    <?= e($locations[$where]['label']) ?>
                  <?php else: ?>
                    <span class="muted">Not shown anywhere</span>
                  <?php endif; ?>
                </td>
                <td class="num"><?= e((string) count((array) $menu['items'])) ?></td>
                <td style="white-space:nowrap">
                  <a class="btn btn-outline-brand btn-sm" href="<?= e(base_url('admin/menus/' . $id . '/edit')) ?>">Edit</a>
                  <form method="post" action="<?= e(base_url('admin/menus/' . $id . '/delete')) ?>" style="display:inline"
                        data-confirm="Delete the menu &quot;<?= e((string) $menu['name']) ?>&quot;? The site will use its standard links in that place.">
                    <?= csrf_field() ?>
                    <button class="btn btn-light btn-sm" type="submit" style="color:#a3231b">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>New menu</h2>
    <?php if (count($menus) >= $maxMenus): ?>
      <p class="muted">You have reached the limit of <?= (int) $maxMenus ?> menus. Delete one you no longer need to add another.</p>
    <?php else: ?>
      <form method="post" action="<?= e(base_url('admin/menus')) ?>">
        <?= csrf_field() ?>
        <div class="field">
          <label for="menu_name">Menu name<span class="req">*</span></label>
          <input class="input" type="text" id="menu_name" name="name" maxlength="60" required value="<?= old('name') ?>" placeholder="Main menu">
        </div>
        <div class="field">
          <label for="menu_location">Show it in</label>
          <select class="select-field" id="menu_location" name="location">
            <option value="">Nowhere yet (draft)</option>
            <?php foreach ($locations as $key => $loc): ?>
              <option value="<?= e($key) ?>" <?= old('location') === $key ? 'selected' : '' ?>><?= e($loc['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="form-help">A place shows one menu. Choosing a place that already has a menu replaces it.</span>
        </div>
        <button class="btn btn-brand" type="submit">Create menu</button>
      </form>
    <?php endif; ?>
  </section>
</div>
