(function () {
  "use strict";

  function init() {
    const root = document.getElementById("rss-news-ticker");
    if (!root) return;
    const track = document.getElementById("rss-ticker-track");
    const status = document.getElementById("rss-ticker-status");
    const prev = document.getElementById("rss-ticker-prev");
    const next = document.getElementById("rss-ticker-next");
    const refresh = document.getElementById("rss-ticker-refresh");
    const reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let items = [];
    let index = 0;
    let timer = null;
    let request = null;
    let hover = false;
    let focused = false;

    function apiUrl() {
      return typeof resolveApiUrl === "function" ? resolveApiUrl("public/external-news.php?limit=8&page=1") : "../../backend/api/public/external-news.php?limit=8&page=1";
    }
    function detailUrl(item) { return `external-news-detail.html?id=${encodeURIComponent(item.id)}`; }
    function setStatus(message, error) { status.textContent = message; root.classList.toggle("is-error", Boolean(error)); }
    function formatDate(value) {
      if (!value) return "";
      const date = new Date(String(value).replace(" ", "T"));
      return Number.isNaN(date.getTime()) ? "" : new Intl.DateTimeFormat("vi-VN", { dateStyle: "short", timeStyle: "short" }).format(date);
    }
    function makeSlide(item) {
      const article = document.createElement("article");
      article.className = "rss-ticker__slide";
      const source = document.createElement("div");
      source.className = "rss-ticker__source";
      source.textContent = item.attribution || item.source_name || "Nguồn tham khảo";
      const link = document.createElement("a");
      link.className = "rss-ticker__title";
      link.href = detailUrl(item);
      link.textContent = item.title || "Tin tham khảo không có tiêu đề";
      const summary = document.createElement("p");
      summary.className = "rss-ticker__summary";
      summary.textContent = item.summary || "Xem trang chi tiết để biết nguồn và thời gian cập nhật.";
      const meta = document.createElement("div");
      meta.className = "rss-ticker__meta";
      meta.textContent = [formatDate(item.published_at), "Xem trong Mạch Tin →"].filter(Boolean).join(" · ");
      article.append(source, link, summary, meta);
      return article;
    }
    function render() {
      track.replaceChildren();
      items.forEach((item) => track.appendChild(makeSlide(item)));
      index = items.length ? Math.min(index, items.length - 1) : 0;
      track.style.transform = items.length ? `translateX(-${index * 100}%)` : "none";
      const disabled = items.length < 2;
      prev.disabled = disabled;
      next.disabled = disabled;
    }
    function stopAutoplay() { if (timer) { window.clearInterval(timer); timer = null; } }
    function restartAutoplay() {
      stopAutoplay();
      if (!reducedMotion && items.length > 1 && !hover && !focused) timer = window.setInterval(() => move(1), 8000);
    }
    function move(step) {
      if (items.length < 2) return;
      index = (index + step + items.length) % items.length;
      track.style.transform = `translateX(-${index * 100}%)`;
      restartAutoplay();
    }
    async function load() {
      if (request) request.abort();
      request = new AbortController();
      const timeout = window.setTimeout(() => request.abort(), 8000);
      if (!items.length) setStatus("Đang cập nhật…", false);
      try {
        const response = await fetch(apiUrl(), { headers: { Accept: "application/json" }, signal: request.signal, cache: "no-store" });
        const payload = await response.json();
        if (!response.ok || !payload.success || !Array.isArray(payload.data)) throw new Error("external-news unavailable");
        items = payload.data.slice(0, 8);
        render();
        setStatus(items.length ? `${items.length} tin tham khảo` : "Chưa có tin tham khảo", false);
        restartAutoplay();
      } catch (_) {
        setStatus(items.length ? "Không thể cập nhật · đang giữ dữ liệu cuối" : "Chưa thể tải tin tham khảo", true);
      } finally {
        window.clearTimeout(timeout);
        request = null;
      }
    }
    prev.addEventListener("click", () => move(-1));
    next.addEventListener("click", () => move(1));
    refresh.addEventListener("click", load);
    root.addEventListener("mouseenter", () => { hover = true; stopAutoplay(); });
    root.addEventListener("mouseleave", () => { hover = false; restartAutoplay(); });
    root.addEventListener("focusin", () => { focused = true; stopAutoplay(); });
    root.addEventListener("focusout", () => { focused = false; restartAutoplay(); });
    document.addEventListener("visibilitychange", () => document.hidden ? stopAutoplay() : restartAutoplay());
    window.setInterval(load, 300000);
    load();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
}());
