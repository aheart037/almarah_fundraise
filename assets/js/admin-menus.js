/* Admin → Menus: add / re-order / remove links, and switch page vs web address. */
(function () {
  "use strict";

  var form = document.getElementById("menuForm");
  var rows = document.getElementById("menuRows");
  var tpl = document.getElementById("menuRowTemplate");
  if (!form || !rows) return;

  var nextIndex = rows.querySelectorAll("[data-row]").length;

  function allRows() { return Array.prototype.slice.call(rows.querySelectorAll("[data-row]")); }

  function renumber() {
    allRows().forEach(function (r, i) {
      var o = r.querySelector("[data-order]");
      if (o) o.value = String(i + 1);
    });
  }

  function syncType(r) {
    var type = r.querySelector("[data-type]").value;
    r.querySelector("[data-target-page]").hidden = type === "custom";
    r.querySelector("[data-target-custom]").hidden = type !== "custom";
  }

  function syncHeading() {
    var sel = form.querySelector("[data-location]");
    var field = form.querySelector("[data-heading-field]");
    if (!sel || !field) return;
    var opt = sel.options[sel.selectedIndex];
    field.hidden = !(opt && opt.getAttribute("data-heading") === "1");
  }

  rows.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-move]");
    if (!btn) return;
    var row = btn.closest("[data-row]");
    if (btn.getAttribute("data-move") === "up" && row.previousElementSibling) {
      rows.insertBefore(row, row.previousElementSibling);
    } else if (btn.getAttribute("data-move") === "down" && row.nextElementSibling) {
      rows.insertBefore(row.nextElementSibling, row);
    }
    renumber();
  });

  rows.addEventListener("change", function (e) {
    var row = e.target.closest("[data-row]");
    if (!row) return;
    if (e.target.matches("[data-type]")) syncType(row);
    if (e.target.matches("[data-remove]")) row.classList.toggle("is-removed", e.target.checked);
  });

  var add = form.querySelector("[data-add-row]");
  if (add && tpl) {
    add.addEventListener("click", function () {
      var max = parseInt(form.getAttribute("data-max-items"), 10) || 30;
      if (allRows().length >= max + 2) return;
      var holder = document.createElement("div");
      holder.innerHTML = tpl.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
      var row = holder.querySelector("[data-row]");
      rows.appendChild(row);
      syncType(row);
      renumber();
      var first = row.querySelector("input[type=text]");
      if (first) first.focus();
    });
  }

  form.addEventListener("change", function (e) { if (e.target.matches("[data-location]")) syncHeading(); });

  allRows().forEach(syncType);
  syncHeading();
  renumber();
})();
