(function () {
  "use strict";

  const ready = (callback) => {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", callback, { once: true });
    else callback();
  };

  ready(() => {
    const packageField = document.querySelector(".mp-package-field");
    if (packageField) {
      const packageInput = packageField.querySelector('input[type="file"][name="_mp_package_upload"]');
      const expectedInput = packageField.querySelector(".mp-package-upload-expected");
      if (packageInput && expectedInput) {
        packageInput.addEventListener("change", () => {
          expectedInput.value = packageInput.files && packageInput.files.length ? "1" : "0";
        });
      }
    }

    const gallery = document.querySelector(".mp-gallery-field");
    if (!gallery || !window.wp || !wp.media) return;

    const list = gallery.querySelector(".mp-gallery-list");
    const input = gallery.querySelector(".mp-gallery-ids");
    const addButton = gallery.querySelector(".mp-gallery-add");
    const emptyState = gallery.querySelector(".mp-gallery-empty");
    const labels = window.medilineTemplateGallery || {};
    let frame = null;
    let draggedItem = null;

    const items = () => Array.from(list.querySelectorAll(".mp-gallery-item"));

    const sync = () => {
      const ids = items().map((item) => item.dataset.id).filter(Boolean);
      input.value = ids.join(",");
      emptyState.hidden = ids.length > 0;
      items().forEach((item, index) => {
        item.classList.toggle("is-cover", index === 0);
        const existing = item.querySelector(".mp-gallery-cover-label");
        if (index === 0 && !existing) {
          const badge = document.createElement("span");
          badge.className = "mp-gallery-cover-label";
          badge.textContent = "CARD COVER";
          item.querySelector(".mp-gallery-thumb").appendChild(badge);
        } else if (index !== 0 && existing) {
          existing.remove();
        }
      });
    };

    const createItem = (attachment) => {
      const item = document.createElement("li");
      item.className = "mp-gallery-item";
      item.dataset.id = String(attachment.id);
      item.draggable = true;

      const drag = document.createElement("span");
      drag.className = "mp-gallery-drag";
      drag.setAttribute("aria-hidden", "true");
      drag.textContent = "⋮⋮";

      const thumb = document.createElement("div");
      thumb.className = "mp-gallery-thumb";
      const image = document.createElement("img");
      const size = attachment.sizes && (attachment.sizes.medium || attachment.sizes.thumbnail);
      image.src = size ? size.url : attachment.url;
      image.alt = attachment.alt || "";
      image.draggable = false;
      thumb.appendChild(image);

      const meta = document.createElement("div");
      meta.className = "mp-gallery-item-meta";
      const title = document.createElement("b");
      title.textContent = attachment.title || "Storefront screenshot";
      const hint = document.createElement("small");
      hint.textContent = "Drag to reorder";
      meta.append(title, hint);

      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "mp-gallery-remove";
      remove.setAttribute("aria-label", labels.remove || "Remove screenshot");
      remove.textContent = "×";

      item.append(drag, thumb, meta, remove);
      return item;
    };

    addButton.addEventListener("click", () => {
      if (!frame) {
        frame = wp.media({
          title: labels.title || "Choose storefront screenshots",
          button: { text: labels.button || "Use selected screenshots" },
          library: { type: "image" },
          multiple: "add",
        });

        frame.on("select", () => {
          const known = new Set(items().map((item) => item.dataset.id));
          frame.state().get("selection").toJSON().forEach((attachment) => {
            const id = String(attachment.id);
            if (known.has(id)) return;
            list.appendChild(createItem(attachment));
            known.add(id);
          });
          sync();
        });
      }
      frame.open();
    });

    list.addEventListener("click", (event) => {
      const remove = event.target.closest(".mp-gallery-remove");
      if (!remove) return;
      remove.closest(".mp-gallery-item").remove();
      sync();
    });

    list.addEventListener("dragstart", (event) => {
      draggedItem = event.target.closest(".mp-gallery-item");
      if (!draggedItem) return;
      draggedItem.classList.add("is-dragging");
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", draggedItem.dataset.id || "");
    });

    list.addEventListener("dragover", (event) => {
      if (!draggedItem) return;
      event.preventDefault();
      const target = event.target.closest(".mp-gallery-item");
      if (!target || target === draggedItem) return;
      const rect = target.getBoundingClientRect();
      const before = event.clientY < rect.top + rect.height / 2;
      list.insertBefore(draggedItem, before ? target : target.nextSibling);
    });

    list.addEventListener("dragend", () => {
      if (draggedItem) draggedItem.classList.remove("is-dragging");
      draggedItem = null;
      sync();
    });

    sync();
  });
})();
