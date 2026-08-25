(function () {
  "use strict";

  const ready = (callback) => {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", callback, { once: true });
    else callback();
  };

  ready(() => {
    const body = document.body;
    const i18n = (window.medilinePartners && window.medilinePartners.i18n) || {};
    const menuToggle = document.querySelector(".menu-toggle");
    const mobilePanel = document.querySelector(".mobile-panel");

    const updateBodyLock = () => {
      const modalOpen = Boolean(document.querySelector(".modal-backdrop:not([hidden])"));
      const menuOpen = mobilePanel && mobilePanel.classList.contains("open");
      body.style.overflow = modalOpen || menuOpen ? "hidden" : "";
    };

    if (menuToggle && mobilePanel) {
      menuToggle.addEventListener("click", () => {
        const open = menuToggle.classList.toggle("open");
        mobilePanel.classList.toggle("open", open);
        menuToggle.setAttribute("aria-expanded", String(open));
        updateBodyLock();
      });
      mobilePanel.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => {
        menuToggle.classList.remove("open");
        mobilePanel.classList.remove("open");
        menuToggle.setAttribute("aria-expanded", "false");
        updateBodyLock();
      }));
    }

    const languageSwitchers = Array.from(document.querySelectorAll("[data-language-switcher]"));
    const closeLanguageSwitchers = (except = null) => {
      languageSwitchers.forEach((switcher) => {
        if (switcher === except) return;
        const button = switcher.querySelector(".language-switcher-button");
        const menu = switcher.querySelector(".language-switcher-menu");
        switcher.classList.remove("open");
        if (button) button.setAttribute("aria-expanded", "false");
        if (menu) menu.hidden = true;
      });
    };
    languageSwitchers.forEach((switcher) => {
      const button = switcher.querySelector(".language-switcher-button");
      const menu = switcher.querySelector(".language-switcher-menu");
      if (!button || !menu) return;
      button.addEventListener("click", (event) => {
        event.stopPropagation();
        const open = !switcher.classList.contains("open");
        closeLanguageSwitchers(switcher);
        switcher.classList.toggle("open", open);
        button.setAttribute("aria-expanded", String(open));
        menu.hidden = !open;
      });
    });
    document.addEventListener("click", (event) => {
      if (!event.target.closest("[data-language-switcher]")) closeLanguageSwitchers();
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeLanguageSwitchers();
    });

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const revealItems = Array.from(document.querySelectorAll(".reveal"));
    if ("IntersectionObserver" in window && !reducedMotion) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("visible");
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });
      revealItems.forEach((item) => observer.observe(item));
    } else {
      revealItems.forEach((item) => item.classList.add("visible"));
    }

    document.querySelectorAll(".faq-list article").forEach((item) => {
      const button = item.querySelector("button");
      const icon = button ? button.querySelector("i") : null;
      if (!button) return;
      button.addEventListener("click", () => {
        const open = item.classList.toggle("open");
        button.setAttribute("aria-expanded", String(open));
        if (icon) icon.textContent = open ? "−" : "+";
      });
    });

    const filterButtons = Array.from(document.querySelectorAll(".template-filters [data-filter]"));
    const templateCards = Array.from(document.querySelectorAll(".template-card[data-template]"));
    filterButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const filter = button.getAttribute("data-filter") || "All";
        filterButtons.forEach((item) => item.classList.toggle("active", item === button));
        let visibleIndex = 0;
        templateCards.forEach((card) => {
          const visible = filter === "All" || card.getAttribute("data-category") === filter;
          card.hidden = !visible;
          card.classList.toggle("offset", visible && visibleIndex % 3 === 1);
          if (visible) {
            visibleIndex += 1;
            card.classList.add("visible");
          }
        });
      });
    });

    const modal = document.querySelector(".modal-backdrop");
    let activeTemplateIndex = -1;
    let activeSlider = null;

    const visibleTemplateCards = () => templateCards.filter((card) => !card.hidden);

    const buildTemplateSlider = (gallery, templateName, previewWrap) => {
      activeSlider = null;
      previewWrap.classList.add("has-gallery");
      previewWrap.replaceChildren();

      const slider = document.createElement("div");
      slider.className = "template-slider";
      slider.setAttribute("aria-label", `${templateName} ${i18n.screenshots || "screenshots"}`);

      const viewport = document.createElement("div");
      viewport.className = "template-slider-viewport";
      const track = document.createElement("div");
      track.className = "template-slider-track";
      viewport.appendChild(track);

      const slides = gallery.map((imageData, index) => {
        const slide = document.createElement("figure");
        const tall = Number(imageData.height) > Number(imageData.width) * 1.3;
        slide.className = `template-slide${tall ? " is-tall" : ""}`;
        slide.setAttribute("aria-hidden", String(index !== 0));

        const image = document.createElement("img");
        image.src = imageData.src;
        if (imageData.srcset) image.srcset = imageData.srcset;
        if (imageData.sizes) image.sizes = imageData.sizes;
        image.alt = imageData.alt || `${templateName} screenshot ${index + 1}`;
        image.width = Number(imageData.width) || 1200;
        image.height = Number(imageData.height) || 800;
        image.loading = index === 0 ? "eager" : "lazy";
        image.decoding = "async";
        image.draggable = false;
        slide.appendChild(image);
        track.appendChild(slide);
        return slide;
      });

      const previous = document.createElement("button");
      previous.type = "button";
      previous.className = "template-slider-arrow is-previous";
      previous.setAttribute("aria-label", i18n.previousScreenshot || "Previous screenshot");
      previous.innerHTML = "<span aria-hidden=\"true\">←</span>";

      const next = document.createElement("button");
      next.type = "button";
      next.className = "template-slider-arrow is-next";
      next.setAttribute("aria-label", i18n.nextScreenshot || "Next screenshot");
      next.innerHTML = "<span aria-hidden=\"true\">→</span>";

      const footer = document.createElement("div");
      footer.className = "template-slider-footer";
      const caption = document.createElement("span");
      caption.className = "template-slider-caption";
      const dots = document.createElement("div");
      dots.className = "template-slider-dots";
      dots.setAttribute("role", "tablist");
      dots.setAttribute("aria-label", i18n.chooseScreenshot || "Choose screenshot");
      const counter = document.createElement("span");
      counter.className = "template-slider-counter";

      let activeIndex = 0;
      const dotButtons = gallery.map((imageData, index) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.setAttribute("role", "tab");
        dot.setAttribute("aria-label", `${i18n.openScreenshot || "Open screenshot"} ${index + 1}`);
        dot.addEventListener("click", () => goTo(index));
        dots.appendChild(dot);
        return dot;
      });

      const goTo = (index) => {
        activeIndex = (index + gallery.length) % gallery.length;
        track.style.transform = `translate3d(-${activeIndex * 100}%, 0, 0)`;
        slides.forEach((slide, slideIndex) => slide.setAttribute("aria-hidden", String(slideIndex !== activeIndex)));
        dotButtons.forEach((dot, dotIndex) => {
          const current = dotIndex === activeIndex;
          dot.classList.toggle("active", current);
          dot.setAttribute("aria-selected", String(current));
          dot.tabIndex = current ? 0 : -1;
        });
        const currentImage = gallery[activeIndex];
        caption.textContent = currentImage.caption || `${(i18n.storefrontScreen || "Storefront screen").toUpperCase()} ${String(activeIndex + 1).padStart(2, "0")}`;
        counter.textContent = `${String(activeIndex + 1).padStart(2, "0")} / ${String(gallery.length).padStart(2, "0")}`;
      };

      const step = (direction) => goTo(activeIndex + direction);
      previous.addEventListener("click", () => step(-1));
      next.addEventListener("click", () => step(1));

      if (gallery.length > 1) {
        viewport.append(previous, next);
      } else {
        dots.classList.add("is-single");
      }

      let pointerStart = null;
      viewport.addEventListener("pointerdown", (event) => {
        if (event.button !== 0 || gallery.length < 2) return;
        pointerStart = { x: event.clientX, y: event.clientY };
      });
      viewport.addEventListener("pointerup", (event) => {
        if (!pointerStart) return;
        const deltaX = event.clientX - pointerStart.x;
        const deltaY = event.clientY - pointerStart.y;
        if (Math.abs(deltaX) > 48 && Math.abs(deltaX) > Math.abs(deltaY)) step(deltaX < 0 ? 1 : -1);
        pointerStart = null;
      });
      viewport.addEventListener("pointercancel", () => { pointerStart = null; });

      footer.append(caption, dots, counter);
      slider.append(viewport, footer);
      previewWrap.appendChild(slider);
      goTo(0);
      activeSlider = { length: gallery.length, step };
    };

    const renderModal = (card) => {
      if (!modal || !card) return;
      let data;
      try {
        data = JSON.parse(card.getAttribute("data-template") || "{}");
      } catch (error) {
        return;
      }
      const preview = card.querySelector(".store-preview");
      const previewWrap = modal.querySelector(".modal-preview-wrap");
      const gallery = Array.isArray(data.gallery) ? data.gallery.filter((image) => image && image.src) : [];
      if (previewWrap && gallery.length) {
        buildTemplateSlider(gallery, data.name || "Store template", previewWrap);
      } else if (preview && previewWrap) {
        activeSlider = null;
        previewWrap.classList.remove("has-gallery");
        previewWrap.replaceChildren(preview.cloneNode(true));
        const expandedPreview = previewWrap.querySelector(".store-preview");
        if (expandedPreview) expandedPreview.classList.add("expanded");
      }
      const values = {
        ".modal-number": `${i18n.templatePreview || "Template preview"} / ${data.number || "01"}`,
        "#template-modal-title": data.name || "",
        ".modal-category": data.category_label || data.category || "",
        ".modal-tagline": data.tagline || "",
        ".modal-description": data.description || "",
        ".modal-tone": data.tone || "",
        ".modal-audience": data.audience || "",
      };
      Object.entries(values).forEach(([selector, value]) => {
        const element = modal.querySelector(selector);
        if (element) element.textContent = value;
      });
      const list = visibleTemplateCards();
      activeTemplateIndex = list.indexOf(card);
      const position = modal.querySelector(".modal-position");
      if (position) position.textContent = `${data.number || String(activeTemplateIndex + 1).padStart(2, "0")} / ${String(list.length).padStart(2, "0")}`;
    };

    const openModal = (card) => {
      if (!modal) return;
      renderModal(card);
      modal.hidden = false;
      updateBodyLock();
      const closeButton = modal.querySelector(".modal-close");
      if (closeButton) closeButton.focus();
    };

    const closeModal = () => {
      if (!modal) return;
      modal.hidden = true;
      activeSlider = null;
      updateBodyLock();
      const list = visibleTemplateCards();
      if (list[activeTemplateIndex]) list[activeTemplateIndex].focus();
    };

    const stepModal = (direction) => {
      const list = visibleTemplateCards();
      if (!list.length) return;
      activeTemplateIndex = (activeTemplateIndex + direction + list.length) % list.length;
      renderModal(list[activeTemplateIndex]);
    };

    templateCards.forEach((card) => card.addEventListener("click", () => openModal(card)));
    if (modal) {
      const closeButton = modal.querySelector(".modal-close");
      if (closeButton) closeButton.addEventListener("click", closeModal);
      modal.addEventListener("mousedown", (event) => { if (event.target === modal) closeModal(); });
      modal.querySelectorAll("[data-modal-step]").forEach((button) => button.addEventListener("click", () => stepModal(Number(button.getAttribute("data-modal-step")))));
      document.addEventListener("keydown", (event) => {
        if (modal.hidden) return;
        if (event.key === "Escape") closeModal();
        if (event.key === "ArrowRight") {
          event.preventDefault();
          if (activeSlider && activeSlider.length > 1) activeSlider.step(1);
          else stepModal(1);
        }
        if (event.key === "ArrowLeft") {
          event.preventDefault();
          if (activeSlider && activeSlider.length > 1) activeSlider.step(-1);
          else stepModal(-1);
        }
      });
    }

    const heroVisual = document.querySelector(".hero-v7-visual");
    const moneyArt = document.querySelector(".money-art");
    if (heroVisual && moneyArt && !reducedMotion) {
      heroVisual.addEventListener("pointermove", (event) => {
        const rect = heroVisual.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        moneyArt.style.setProperty("--tilt-x", `${-y * 5}deg`);
        moneyArt.style.setProperty("--tilt-y", `${x * 7}deg`);
      });
      heroVisual.addEventListener("pointerleave", () => {
        moneyArt.style.setProperty("--tilt-x", "0deg");
        moneyArt.style.setProperty("--tilt-y", "0deg");
      });
    }

    document.querySelectorAll(".password-toggle").forEach((button) => {
      button.addEventListener("click", () => {
        const field = button.closest(".password-field");
        const input = field ? field.querySelector("input") : null;
        if (!input) return;
        const show = input.type === "password";
        input.type = show ? "text" : "password";
        button.textContent = show ? (i18n.hide || "Hide") : (i18n.show || "Show");
        button.setAttribute("aria-label", show ? (i18n.hide || "Hide") : (i18n.show || "Show"));
      });
    });
  });
})();
