(() => {
  function escapeHtml(value) {
    const div = document.createElement("div");
    div.textContent = value == null ? "" : String(value);
    return div.innerHTML;
  }

  function getMessageTarget(form) {
    const selector = form.getAttribute("data-message-target");
    if (selector) {
      const el = document.querySelector(selector);
      if (el) return el;
    }
    const inside = form.querySelector("[data-api-message]");
    if (inside) return inside;
    const existing = document.getElementById("message");
    if (existing) return existing;

    const div = document.createElement("div");
    div.setAttribute("data-api-message", "1");
    form.prepend(div);
    return div;
  }

  function setBusy(form, busy) {
    const buttons = form.querySelectorAll("button, input[type='submit']");
    buttons.forEach((b) => {
      if (busy) {
        if (!b.dataset._oldText) b.dataset._oldText = b.textContent || "";
        b.disabled = true;
      } else {
        b.disabled = false;
      }
    });
  }

  async function handleSubmit(e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const apiUrl = form.getAttribute("data-api") || form.getAttribute("action");
    if (!apiUrl) return;

    e.preventDefault();

    const msgEl = getMessageTarget(form);
    msgEl.innerHTML = "";
    setBusy(form, true);

    const hasFile = form.querySelector("input[type='file']");
    const useFormData =
      hasFile ||
      (form.enctype && form.enctype.toLowerCase().includes("multipart/form-data"));

    let body;
    let headers = {};
    if (useFormData) {
      body = new FormData(form);
    } else {
      body = new URLSearchParams(new FormData(form));
      headers["Content-Type"] =
        "application/x-www-form-urlencoded;charset=UTF-8";
    }

    try {
      const res = await fetch(apiUrl, {
        method: (form.getAttribute("method") || "POST").toUpperCase(),
        credentials: "same-origin",
        headers,
        body,
      });
      const data = await res.json().catch(() => null);
      if (!data || typeof data !== "object") {
        throw new Error("Invalid server response.");
      }

      if (data.success) {
        if (data.message) {
          msgEl.innerHTML =
            '<div class="alert alert-success py-2">' +
            escapeHtml(data.message) +
            "</div>";
        }
        if (data.redirect) {
          window.location.href = data.redirect;
          return;
        }
        if (data.reload) {
          setTimeout(() => window.location.reload(), 500);
          return;
        }
      } else {
        msgEl.innerHTML =
          '<div class="alert alert-danger py-2">' +
          escapeHtml(data.message || "Request failed.") +
          "</div>";
      }
    } catch (err) {
      msgEl.innerHTML =
        '<div class="alert alert-danger py-2">' +
        escapeHtml(err && err.message ? err.message : "Network error.") +
        "</div>";
    } finally {
      setBusy(form, false);
    }
  }

  document.addEventListener("submit", (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.hasAttribute("data-api")) return;
    handleSubmit(e);
  });
})();

