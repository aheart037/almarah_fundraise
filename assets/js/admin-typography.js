/* Admin → Fonts: live preview, weight list per font, size slider. */
(function () {
  "use strict";

  var form = document.getElementById("typographyForm");
  if (!form) return;

  var fonts = {};
  var names = {};
  try { fonts = JSON.parse(form.getAttribute("data-fonts") || "{}"); } catch (e) {}
  try { names = JSON.parse(form.getAttribute("data-weight-names") || "{}"); } catch (e) {}

  var baseSize = { heading: 32, sub: 22, body: 16 };
  var originalFamily = {};

  function section(role) { return form.querySelector('[data-role="' + role + '"]'); }
  function value(role, sel) { var el = section(role).querySelector(sel); return el ? el.value : ""; }
  function preview(role) { return document.querySelector('[data-preview="' + role + '"]'); }

  // Remember what the preview looks like with the site's own font.
  ["heading", "sub", "body"].forEach(function (role) {
    var p = preview(role);
    if (p) originalFamily[role] = getComputedStyle(p).fontFamily;
  });

  // The font a role really uses ("same as headings" follows the heading choice).
  function effectiveFont(role) {
    var key = value(role, "[data-font-select]");
    if (role === "sub" && key === "") key = value("heading", "[data-font-select]");
    return key;
  }

  // Offer only the weights the chosen font actually has.
  function rebuildWeights(role) {
    var sel = section(role).querySelector("[data-weight-select]");
    var font = fonts[effectiveFont(role)] || { min: 100, max: 900 };
    var keep = parseInt(sel.value, 10) || 0;
    var html = '<option value="0">Site default</option>';
    for (var w = 100; w <= 900; w += 100) {
      if (w >= font.min && w <= font.max) {
        html += '<option value="' + w + '">' + (names[w] || w) + " (" + w + ")</option>";
      }
    }
    sel.innerHTML = html;
    if (keep !== 0) keep = Math.max(font.min, Math.min(font.max, keep));
    sel.value = String(keep);
    if (sel.value !== String(keep)) sel.value = "0";
  }

  function update(role) {
    var p = preview(role);
    if (!p) return;
    var key = effectiveFont(role);
    var font = fonts[key];
    p.style.fontFamily = font && font.css ? font.css : originalFamily[role];

    var weight = parseInt(value(role, "[data-weight-select]"), 10) || 0;
    p.style.fontWeight = weight ? String(weight) : "";

    var size = parseInt(value(role, "[data-size-number]"), 10) || 100;
    p.style.fontSize = (baseSize[role] * size / 100) + "px";
  }

  function updateAll() { ["heading", "sub", "body"].forEach(update); }

  form.addEventListener("change", function (e) {
    var role = e.target.closest("[data-role]");
    role = role && role.getAttribute("data-role");
    if (!role) return;

    if (e.target.matches("[data-font-select]")) {
      rebuildWeights(role);
      if (role === "heading") rebuildWeights("sub");   // "same as headings" follows
    }
    updateAll();
  });

  // Slider and number box stay in step.
  form.addEventListener("input", function (e) {
    var box = e.target.closest("[data-role]");
    if (!box) return;
    var range = box.querySelector("[data-size-range]");
    var number = box.querySelector("[data-size-number]");
    if (e.target === range) number.value = range.value;
    else if (e.target === number && number.value !== "") range.value = number.value;
    updateAll();
  });

  updateAll();
})();
