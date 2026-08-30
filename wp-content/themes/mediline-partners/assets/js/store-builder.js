(() => {
  const config = window.medilineBuilder || {};
  const app = document.querySelector("[data-builder-app]");
  if (!app) return;

  const cards = [...document.querySelectorAll("[data-builder-template]")];
  const preview = document.querySelector("[data-builder-preview]");
  const configPanel = document.querySelector("[data-builder-config]");
  const form = document.querySelector("[data-builder-form]");
  const status = document.querySelector("[data-builder-status]");
  const ready = document.querySelector("[data-builder-ready]");
  let activeTemplate = null;
  let previewIndex = 0;

  const readCard = (card) => {
    try { return JSON.parse(card.getAttribute("data-template") || "{}"); }
    catch { return {}; }
  };

  const escapeText = (value) => String(value || "");

  const renderPreview = (data) => {
    const media = preview?.querySelector("[data-preview-media]");
    if (!media) return;
    media.replaceChildren();
    const gallery = Array.isArray(data.gallery) ? data.gallery : [];
    previewIndex = 0;
    if (!gallery.length) {
      const empty = document.createElement("div");
      empty.className = "builder-preview-empty";
      empty.textContent = "No uploaded screenshots yet.";
      media.appendChild(empty);
    } else {
      const stage = document.createElement("div");
      stage.className = "builder-preview-stage";
      const image = document.createElement("img");
      image.alt = gallery[0].alt || `${data.name} preview`;
      stage.appendChild(image);
      const footer = document.createElement("div");
      footer.className = "builder-preview-footer";
      const prev = document.createElement("button"); prev.type = "button"; prev.textContent = "←";
      const counter = document.createElement("span");
      const next = document.createElement("button"); next.type = "button"; next.textContent = "→";
      const update = () => {
        const item = gallery[previewIndex];
        image.src = item.src || item.cover;
        image.srcset = item.srcset || "";
        image.alt = item.alt || `${data.name} preview ${previewIndex + 1}`;
        counter.textContent = `${String(previewIndex + 1).padStart(2, "0")} / ${String(gallery.length).padStart(2, "0")}`;
      };
      prev.addEventListener("click", () => { previewIndex = (previewIndex - 1 + gallery.length) % gallery.length; update(); });
      next.addEventListener("click", () => { previewIndex = (previewIndex + 1) % gallery.length; update(); });
      footer.append(prev, counter, next);
      media.append(stage, footer);
      update();
    }
    preview.querySelector("[data-preview-category]").textContent = data.category || "";
    preview.querySelector("[data-preview-title]").textContent = data.name || "";
    preview.querySelector("[data-preview-description]").textContent = data.description || "";
  };

  const openPreview = (card) => {
    activeTemplate = readCard(card);
    renderPreview(activeTemplate);
    preview.hidden = false;
    document.body.classList.add("builder-overlay-open");
  };
  const closePreview = () => {
    if (preview) preview.hidden = true;
    document.body.classList.remove("builder-overlay-open");
  };

  const openConfig = (data) => {
    if (!data || !data.package_ready) return;
    activeTemplate = data;
    closePreview();
    form?.reset();
    if (form) {
      form.elements.template_id.value = data.id || "";
      form.elements.admin_username.value = "admin";
      const en = form.querySelector('input[name="languages[]"][value="en"]');
      if (en) en.checked = true;
    }
    configPanel?.setAttribute("aria-hidden", "false");
    configPanel?.classList.add("open");
    document.body.classList.add("builder-overlay-open");
    const title = document.querySelector("[data-config-title]");
    const reviews = [...document.querySelectorAll("[data-review-template]")];
    const version = document.querySelector("[data-review-version]");
    const cover = document.querySelector("[data-config-cover]");
    const packageMeta = document.querySelector("[data-config-package-meta]");
    if (title) title.textContent = "Configure storefront";
    reviews.forEach((review) => { review.textContent = data.name || "—"; });
    if (version) version.textContent = data.package_version ? `ZIP ${data.package_version}` : "";
    if (packageMeta) packageMeta.textContent = data.package_version ? `Theme ${data.package_version} · installer included` : "Installer package";
    if (cover) {
      const first = Array.isArray(data.gallery) && data.gallery.length ? data.gallery[0] : null;
      if (first?.src || first?.cover) {
        cover.src = first.src || first.cover;
        cover.alt = `${data.name || "Storefront"} preview`;
        cover.hidden = false;
      } else {
        cover.removeAttribute("src");
        cover.alt = "";
        cover.hidden = true;
      }
    }
    if (ready) ready.hidden = true;
    if (form) form.hidden = false;
    if (status) {
      status.textContent = "";
      status.className = "builder-form-status";
    }
  };
  const closeConfig = () => {
    configPanel?.setAttribute("aria-hidden", "true");
    configPanel?.classList.remove("open");
    document.body.classList.remove("builder-overlay-open");
  };

  cards.forEach((card) => {
    card.querySelector("[data-template-preview]")?.addEventListener("click", () => openPreview(card));
    card.querySelector("[data-use-template]")?.addEventListener("click", () => openConfig(readCard(card)));
  });
  preview?.querySelector("[data-close-preview]")?.addEventListener("click", closePreview);
  preview?.querySelector("[data-preview-use]")?.addEventListener("click", () => openConfig(activeTemplate));
  preview?.addEventListener("mousedown", (event) => { if (event.target === preview) closePreview(); });
  configPanel?.querySelector("[data-config-close]")?.addEventListener("click", closeConfig);

  const passwordInput = form?.elements.admin_password;
  form?.querySelector("[data-generate-password]")?.addEventListener("click", () => {
    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*";
    const values = crypto.getRandomValues(new Uint32Array(20));
    passwordInput.value = [...values].map((value) => chars[value % chars.length]).join("");
    passwordInput.type = "text";
  });
  form?.querySelector("[data-toggle-password]")?.addEventListener("click", (event) => {
    const show = passwordInput.type === "password";
    passwordInput.type = show ? "text" : "password";
    event.currentTarget.textContent = show ? "Hide" : "Show";
  });

  form?.querySelector("[data-primary-language]")?.addEventListener("change", (event) => {
    const checkbox = form.querySelector(`input[name="languages[]"][value="${CSS.escape(event.target.value)}"]`);
    if (checkbox) checkbox.checked = true;
  });

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    if (status) {
      status.textContent = "Preparing installation package…";
      status.className = "builder-form-status is-loading";
    }
    const data = new FormData(form);
    const payload = Object.fromEntries([...data.entries()].filter(([key]) => key !== "languages[]"));
    payload.languages = data.getAll("languages[]");
    payload.template_id = Number(payload.template_id || 0);
    try {
      const headers = {
        "Content-Type": "application/json",
        "X-Mediline-CSRF": config.csrf || "",
      };
      if (config.restNonce) headers["X-WP-Nonce"] = config.restNonce;

      const response = await fetch(config.packageEndpoint, {
        method: "POST",
        credentials: "include",
        headers,
        body: JSON.stringify(payload),
      });
      const contentType = response.headers.get("content-type") || "";
      const result = contentType.includes("application/json")
        ? await response.json()
        : { message: (await response.text()).replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim() };
      if (!response.ok) throw new Error(result?.message || "Could not generate package.");
      form.hidden = true;
      ready.hidden = false;
      ready.querySelector("[data-builder-download]").href = result.download_url;
      const command = ready.querySelector("[data-install-command]");
      if (command) command.textContent = result.install_command || "Generate a fresh installer command.";
      ready.dataset.installCommand = result.install_command || "";
      ready.querySelector("[data-installation-id]").textContent = `Installation ${result.installation_id}`;
      if (status) status.className = "builder-form-status is-success";
    } catch (error) {
      if (status) {
        status.textContent = error.message;
        status.className = "builder-form-status is-error";
      }
    } finally {
      submit.disabled = false;
    }
  });

  document.querySelector("[data-copy-command]")?.addEventListener("click", async (event) => {
    const command = ready?.dataset.installCommand || ready?.querySelector("[data-install-command]")?.textContent || "";
    if (!command) return;
    await navigator.clipboard.writeText(command);
    const original = event.currentTarget.textContent;
    event.currentTarget.textContent = "Copied";
    setTimeout(() => { event.currentTarget.textContent = original || "Copy"; }, 1600);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    if (preview && !preview.hidden) closePreview();
    else if (configPanel?.classList.contains("open")) closeConfig();
  });
})();
