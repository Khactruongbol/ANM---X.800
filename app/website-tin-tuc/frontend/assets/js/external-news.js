(function () {
  "use strict";

  function apiUrl(path) { return typeof resolveApiUrl === "function" ? resolveApiUrl(path) : `../../backend/api/${path}`; }
  function init() {
    const mount = document.getElementById("external-news-detail");
    if (!mount) return;
    if (typeof initPublicHeader === "function") initPublicHeader("");
    if (typeof initPublicFooter === "function") initPublicFooter();
    const id = Number(new URLSearchParams(window.location.search).get("id"));
    if (!Number.isInteger(id) || id < 1) {
      mount.innerHTML = "<p class=\"external-news__error\">Tin tham khảo không hợp lệ.</p>";
      return;
    }
    fetch(apiUrl(`public/external-news-detail.php?id=${encodeURIComponent(id)}`), { headers: { Accept: "application/json" }, cache: "no-store" })
      .then((response) => response.json().then((payload) => ({ response, payload })))
      .then(({ response, payload }) => {
        if (!response.ok || !payload.success || !payload.data) throw new Error("not found");
        const item = payload.data;
        let sourceUrl;
        try {
          sourceUrl = new URL(item.original_url);
          if (!["http:", "https:"].includes(sourceUrl.protocol)) throw new Error("bad protocol");
        } catch (_) { throw new Error("bad source URL"); }
        document.title = `${item.title} - Tin tham khảo - Mạch Tin`;
        mount.replaceChildren();
        const label = document.createElement("div");
        label.className = "external-news__label";
        label.textContent = item.attribution || item.source_name || "Nguồn bên ngoài";
        const title = document.createElement("h1");
        title.textContent = item.title;
        const summary = document.createElement("p");
        summary.className = "external-news__summary";
        summary.textContent = item.summary || "Nguồn không cung cấp tóm tắt.";
        const meta = document.createElement("p");
        meta.className = "external-news__meta";
        meta.textContent = item.published_at ? `Cập nhật: ${new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(String(item.published_at).replace(" ", "T")))}` : "Thời gian cập nhật không được cung cấp";
        const note = document.createElement("p");
        note.className = "external-news__notice";
        note.textContent = "Đây là metadata từ nguồn RSS bên ngoài, không phải toàn văn bài biên tập của Mạch Tin.";
        const actions = document.createElement("div");
        actions.className = "external-news__actions";
        const original = document.createElement("a");
        original.className = "external-news__original";
        original.href = sourceUrl.href;
        original.target = "_blank";
        original.rel = "noopener noreferrer";
        original.textContent = `Đọc bài gốc tại ${item.source_name || sourceUrl.hostname} →`;
        const back = document.createElement("a");
        back.href = "index.html#rss-news-ticker";
        back.className = "external-news__back";
        back.textContent = "← Quay lại Tin tham khảo";
        actions.append(original, back);
        mount.append(label, title, summary, meta, note, actions);
      })
      .catch(() => {
        mount.innerHTML = "<p class=\"external-news__error\">Không thể tải tin tham khảo hoặc tin đã được ẩn.</p><a class=\"external-news__back\" href=\"index.html#rss-news-ticker\">← Quay lại trang chủ</a>";
      });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
}());
