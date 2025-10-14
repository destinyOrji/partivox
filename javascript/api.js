// Minimal global API utilities for Partivox
// window.AppAPI
(function () {
  const API_BASE_URL = '/';

  function toast(msg, type = 'info', ms = 2200) {
    let c = document.getElementById('toastContainer');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toastContainer';
      c.style.cssText = 'position:fixed;top:16px;right:16px;z-index:99999;display:flex;flex-direction:column;gap:8px';
      document.body.appendChild(c);
    }
    const t = document.createElement('div');
    t.style.cssText = 'min-width:220px;max-width:360px;padding:10px 14px;border-radius:10px;font-weight:600;color:#fff;box-shadow:0 8px 30px rgba(0,0,0,.35)';
    // Lemon green theme defaults
    t.style.background = type === 'success' ? '#10b981'
      : type === 'error' ? '#ef4444'
      : type === 'warning' ? '#f59e0b'
      : '#caf403';
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => { try { c.removeChild(t); } catch(_){} }, ms);
  }

  async function fetchWithTimeout(url, options = {}, timeoutMs = 10000) {
    const controller = new AbortController();
    const id = setTimeout(() => controller.abort(), timeoutMs);
    try { return await fetch(url, { ...options, signal: controller.signal }); }
    finally { clearTimeout(id); }
  }

  async function getValidToken() {
    let token = localStorage.getItem('authToken');
    if (!token) {
      try {
        const r = await fetchWithTimeout(`${API_BASE_URL}api/twitter/get-token.php`, { method:'GET', credentials:'include' }, 8000);
        if (r.ok) {
          const d = await r.json().catch(() => null);
          if (d && d.status === 'success' && d.token) {
            token = d.token; localStorage.setItem('authToken', token);
          }
        }
      } catch (_) { /* silent */ }
    }
    return token;
  }

  async function apiFetch(path, opts = {}) {
    const token = await getValidToken();
    const headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers || {});
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const res = await fetchWithTimeout(`${API_BASE_URL}${path.replace(/^\//,'')}`, {
      method: opts.method || 'GET',
      headers,
      body: opts.body ? (typeof opts.body === 'string' ? opts.body : JSON.stringify(opts.body)) : undefined
    }, opts.timeoutMs || 12000);
    let json = null; try { json = await res.json(); } catch (_) {}
    return { res, json };
  }

  window.AppAPI = { API_BASE_URL, toast, fetchWithTimeout, getValidToken, apiFetch };
})();
