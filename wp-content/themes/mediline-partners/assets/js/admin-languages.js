(function () {
  "use strict";

  const rows = document.querySelector("[data-language-rows]");
  const template = document.querySelector("[data-language-template]");
  const addButton = document.querySelector("[data-add-language]");

  if (!rows || !template || !addButton) return;

  const refresh = () => {
    rows.querySelectorAll("[data-language-row]").forEach((row, index) => {
      const order = row.querySelector(".mp-language-order");
      if (order) order.textContent = String(index + 1).padStart(2, "0");
      row.querySelectorAll("[name]").forEach((field) => {
        field.name = field.name.replace(/languages\[\d+\]/, `languages[${index}]`);
      });
    });
  };

  const bindRemove = (scope) => {
    scope.querySelectorAll("[data-remove-language]").forEach((button) => {
      if (button.dataset.bound) return;
      button.dataset.bound = "true";
      button.addEventListener("click", () => {
        const row = button.closest("[data-language-row]");
        if (row) row.remove();
        refresh();
      });
    });
  };

  addButton.addEventListener("click", () => {
    const fragment = template.content.cloneNode(true);
    rows.appendChild(fragment);
    refresh();
    bindRemove(rows);
    const added = rows.lastElementChild;
    const code = added ? added.querySelector('input[name*="[code]"]') : null;
    if (code) code.focus();
  });

  bindRemove(rows);
  refresh();
})();
