(() => {
  const dashboardBoard = document.getElementById("dashboardBoard");
  const lastCheckedText = document.getElementById("lastCheckedText");
  const checkBtn = document.getElementById("manualCheckBtn");

  function statusClass(status) {
    if (status === "green") return "status-green";
    if (status === "orange") return "status-orange";
    if (status === "pending") return "status-pending";
    return "status-red";
  }

  function renderRows(results) {
    if (!Array.isArray(results) || results.length === 0) {
      dashboardBoard.innerHTML = '<div class="text-center text-muted py-4">No checks run yet.</div>';
      return;
    }

    const itemHtml = (row) => {
      const role = String(row.role ?? "").trim().toUpperCase();
      const serverName = String(row.hostname ?? "").trim();
      const isPending = row.status === "pending";
      const machineType = String(row.machine_type ?? "vm").toLowerCase();
      const isCaddy = Boolean(row.is_caddy);
      const machineIcon = machineType === "physical" ? "🖥️" : "🧬";
      const caddyIcon = isCaddy ? " 🛡️" : "";
      return `<div class="server-item">
        <div class="server-name"><span class="status-dot ${statusClass(row.status)} me-2">${escapeHtml(role || "?")}</span>${escapeHtml(serverName)} <span title="${escapeHtml(machineType)}">${machineIcon}</span><span title="caddy">${caddyIcon}</span></div>
        ${
          isPending
            ? `<div class="placeholder-glow small mb-1"><span class="placeholder col-10"></span></div>
               <div class="placeholder-glow small"><span class="placeholder col-6"></span></div>`
            : `<div class="small text-muted">${escapeHtml(String(row.url ?? ""))}</div>
               <div class="small text-muted">${escapeHtml(String(row.internal_ip ?? ""))}</div>`
        }
      </div>`;
    };

    const core = results.filter((r) => String(r.group ?? "").toUpperCase() === "CORE");
    const ozrog = results.filter((r) => String(r.group ?? "").toUpperCase() === "OZROG");
    const ozbell = results.filter((r) => String(r.group ?? "").toUpperCase() === "OZBELL");
    const torrog = results.filter((r) => String(r.group ?? "").toUpperCase() === "TORROG");

    const boardHtml = `
    <div class="row g-3">
      <h5>Load Balanced</h5>
      <div class="col-12">
        <div class="board-box p-3 mb-3">
          <div class="row g-3">
            ${core.map((row) => `<div class="col-md-6">${itemHtml(row)}</div>`).join("")}
          </div>
        </div>
      </div>
    </div>
    <div class="row g-3">
      <h5>Individual Servers</h5>
      <div class="col-lg-4">
        <div class="board-box p-3 h-100">
          <div class="group-title mb-2">OZROG</div>
          ${ozrog.map(itemHtml).join("")}
        </div>
      </div>
      <div class="col-lg-4">
        <div class="board-box p-3 h-100">
          <div class="group-title mb-2">OZBELL</div>
          ${ozbell.map(itemHtml).join("")}
        </div>
      </div>
      <div class="col-lg-4">
        <div class="board-box p-3 h-100">
          <div class="group-title mb-2">TORROG</div>
          ${torrog.map(itemHtml).join("")}
        </div>
      </div>
    </div>
    `;

    dashboardBoard.innerHTML = boardHtml;
  }

  function buildPendingRows() {
    const blueprints = Array.isArray(window.SERVER_BLUEPRINTS) ? window.SERVER_BLUEPRINTS : [];
    return blueprints.map((row) => ({ ...row, status: "pending" }));
  }

  function escapeHtml(value) {
    return value
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  async function runCheck() {
    checkBtn.disabled = true;
    const defaultBtnHtml = checkBtn.innerHTML;
    checkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Checking';
    const pendingRows = buildPendingRows();
    const byUrl = new Map(pendingRows.map((row) => [String(row.url), row]));
    renderRows(Array.from(byUrl.values()));
    lastCheckedText.textContent = "Last checked: checking...";
    try {
      const response = await fetch("api/check-stream.php", { cache: "no-store" });
      if (!response.ok) {
        throw new Error(`Check failed with HTTP ${response.status}`);
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = "";
      let done = false;

      while (!done) {
        const chunk = await reader.read();
        done = chunk.done;
        buffer += decoder.decode(chunk.value || new Uint8Array(), { stream: !done });

        const lines = buffer.split("\n");
        buffer = lines.pop() ?? "";

        for (const line of lines) {
          const trimmed = line.trim();
          if (!trimmed) continue;

          const payload = JSON.parse(trimmed);
          if (payload.type === "result" && payload.result?.url) {
            byUrl.set(String(payload.result.url), payload.result);
            renderRows(Array.from(byUrl.values()));
          } else if (payload.type === "done") {
            lastCheckedText.textContent = `Last checked: ${payload.checked_at || "unknown"}`;
          }
        }
      }
    } catch (err) {
      console.error(err);
      lastCheckedText.textContent = `Last checked: error (${err.message})`;
    } finally {
      checkBtn.innerHTML = defaultBtnHtml;
      checkBtn.disabled = false;
    }
  }

  checkBtn.addEventListener("click", runCheck);
  renderRows(Array.isArray(window.INITIAL_RESULTS) ? window.INITIAL_RESULTS : []);
  runCheck();
})();
