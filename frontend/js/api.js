const API_BASE = 'http://127.0.0.1:8020/api';

function getToken() {
  return localStorage.getItem('token');
}

function getUser() {
  const raw = localStorage.getItem('user');
  return raw ? JSON.parse(raw) : null;
}

function setSession(user, token) {
  localStorage.setItem('user', JSON.stringify(user));
  localStorage.setItem('token', token);
}

function clearSession() {
  localStorage.removeItem('user');
  localStorage.removeItem('token');
}

function isLoggedIn() {
  return !!getToken();
}

function isAdmin() {
  const user = getUser();
  return !!user && user.role === 'admin';
}

async function apiRequest(path, { method = 'GET', body = null, auth = false } = {}) {
  const headers = { 'Accept': 'application/json' };
  if (body) headers['Content-Type'] = 'application/json';
  if (auth) {
    const token = getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  let data = null;
  try {
    data = await res.json();
  } catch (e) {
    data = null;
  }

  if (!res.ok) {
    const message = (data && (data.message || Object.values(data.errors || {})[0]?.[0])) || 'Request failed';
    const error = new Error(message);
    error.status = res.status;
    error.data = data;
    throw error;
  }

  return data;
}

function renderNav(activePage = '') {
  const nav = document.getElementById('nav');
  if (!nav) return;

  const loggedIn = isLoggedIn();
  const admin = isAdmin();
  const user = getUser();
  const root = window.location.pathname.includes('/admin/') ? '../' : '';

  let links = `<a href="${root}index.html">Storefront</a>`;
  if (loggedIn) {
    links += ` <a href="${root}cart.html">Cart</a> <a href="${root}orders.html">My Orders</a>`;
    if (admin) {
      links += ` <a href="${root}admin/products.html">Manage Products</a> <a href="${root}admin/orders.html">Manage Orders</a>`;
    }
    links += ` <span class="nav-user">${user.name} (${user.role})</span> <a href="#" id="logoutLink">Logout</a>`;
  } else {
    links += ` <a href="${root}login.html">Login</a> <a href="${root}register.html">Register</a>`;
  }

  nav.innerHTML = links;

  const logoutLink = document.getElementById('logoutLink');
  if (logoutLink) {
    logoutLink.addEventListener('click', async (e) => {
      e.preventDefault();
      try {
        await apiRequest('/logout', { method: 'POST', auth: true });
      } catch (e) {
        // ignore network errors on logout
      }
      clearSession();
      window.location.href = 'login.html';
    });
  }
}

function showMessage(elementId, message, isError = true) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.textContent = message;
  el.className = isError ? 'message error' : 'message success';
}
