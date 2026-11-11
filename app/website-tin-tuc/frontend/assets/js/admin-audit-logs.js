(function () {
  "use strict";

  const state = { page: 1, limit: 25 };

  function cell(row, value, className) {
    const td = document.createElement("td");
    if (className) td.className = className;
    td.textContent = value === null || value === undefined || value === "" ? "—" : String(value);
    row.appendChild(td);
  }

  function renderRows(rows) {
    const target = document.getElementById("audit-log-rows");
    target.replaceChildren();
    if (!rows.length) {
      const row = document.createElement("tr");
      const empty = document.createElement("td");
      empty.colSpan = 7;
      empty.textContent = "Chưa có bản ghi phù hợp.";
      row.appendChild(empty);
      target.appendChild(row);
      return;
    }
    rows.forEach((item) => {
      const row = document.createElement("tr");
      cell(row, item.created_at);
      cell(row, item.username || "guest");
      cell(row, item.role);
      cell(row, item.action);
      cell(row, [item.target_type, item.target_id].filter(Boolean).join(" #"));
      cell(row, item.ip_address);
      cell(row, item.detail);
      target.appendChild(row);
    });
  }

  function setStatus(message, isError) {
    const status = document.getElementById("audit-log-status");
    status.textContent = message;
    status.className = isError ? "admin-inline-status is-error" : "admin-inline-status";
  }

  async function loadLogs() {
    setStatus("Đang tải…", false);
    const query = new URLSearchParams({ page: String(state.page), limit: String(state.limit) });
    const action = document.getElementById("audit-action").value.trim();
    const username = document.getElementById("audit-username").value.trim();
    if (action) query.set("action", action);
    if (username) query.set("username", username);
    const payload = await safeFetchJson(resolveApiUrl(`admin/audit-logs.php?${query}`), { credentials: "include" });
    if (!payload.success) {
      renderRows([]);
      setStatus(payload.message || "Không thể tải audit log.", true);
      return;
    }
    renderRows(Array.isArray(payload.data) ? payload.data : []);
    const pagination = payload.pagination || {};
    setStatus(`Trang ${pagination.current_page || 1} · ${pagination.total_records || 0} bản ghi`, false);
  }

  async function verifyChain() {
    const payload = await safeFetchJson(resolveApiUrl("admin/audit-verify.php"), { credentials: "include" });
    setStatus(payload.success ? "Hash chain hợp lệ." : (payload.message || "Hash chain không hợp lệ."), !payload.success);
  }

  function init() {
    const mount = document.getElementById("workspace-content");
    if (!mount) return;
    mount.innerHTML = `
      <section class="admin-card">
        <div class="admin-card__header">
          <div class="admin-card__title-group"><h2 class="admin-card__title">Nhật ký hệ thống</h2></div>
          <div class="admin-card__toolbar">
            <input id="audit-action" class="admin-search-input" type="search" placeholder="Lọc action">
            <input id="audit-username" class="admin-search-input" type="search" placeholder="Lọc username">
            <button id="audit-refresh" class="admin-btn admin-btn--default" type="button">Làm mới</button>
            <button id="audit-verify" class="admin-btn admin-btn--primary" type="button">Xác minh hash</button>
          </div>
        </div>
        <div id="audit-log-status" class="admin-inline-status" aria-live="polite"></div>
        <div class="admin-table-responsive">
          <table class="admin-table">
            <thead><tr><th>Thời gian</th><th>Người dùng</th><th>Vai trò</th><th>Action</th><th>Đối tượng</th><th>IP</th><th>Chi tiết</th></tr></thead>
            <tbody id="audit-log-rows"></tbody>
          </table>
        </div>
      </section>`;
    document.getElementById("audit-refresh").addEventListener("click", loadLogs);
    document.getElementById("audit-verify").addEventListener("click", verifyChain);
    document.getElementById("audit-action").addEventListener("keydown", (event) => { if (event.key === "Enter") loadLogs(); });
    document.getElementById("audit-username").addEventListener("keydown", (event) => { if (event.key === "Enter") loadLogs(); });
    loadLogs();
  }

  document.addEventListener("DOMContentLoaded", init);
})();
