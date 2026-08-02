const API_BASE = 'http://127.0.0.1:8000/api';

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

  let links = '';
  if (!admin) {
    links += `<li class="nav-item"><a class="nav-link" href="${root}index.html"><i class="bi bi-shop me-1"></i>Storefront</a></li>`;
  }
  if (loggedIn) {
    if (!admin) {
      links += `<li class="nav-item"><a class="nav-link" href="${root}cart.html"><i class="bi bi-cart3 me-1"></i>Cart</a></li>`;
      links += `<li class="nav-item"><a class="nav-link" href="${root}orders.html"><i class="bi bi-box-seam me-1"></i>My Orders</a></li>`;
      links += `<li class="nav-item"><a class="nav-link" href="${root}sell.html"><i class="bi bi-tag me-1"></i>Sell a Product</a></li>`;
    }
    if (admin) {
      links += `<li class="nav-item"><a class="nav-link" href="${root}admin/products.html"><i class="bi bi-boxes me-1"></i>Manage Products</a></li>`;
      links += `<li class="nav-item"><a class="nav-link" href="${root}admin/categories.html"><i class="bi bi-tags me-1"></i>Manage Categories</a></li>`;
      links += `<li class="nav-item"><a class="nav-link" href="${root}admin/orders.html"><i class="bi bi-clipboard-check me-1"></i>Manage Orders</a></li>`;
      links += `<li class="nav-item"><a class="nav-link" href="${root}admin/dashboard.html"><i class="bi bi-graph-up me-1"></i>Dashboard</a></li>`;
    }
    links += `<li class="nav-item"><a class="nav-link" href="${root}account.html"><i class="bi bi-person-circle me-1"></i>My Account</a></li>`;
    links += `<li class="nav-item d-flex align-items-lg-center py-2 py-lg-0"><span class="badge rounded-pill text-bg-light nav-user-badge">${user.name} (${user.role})</span></li>`;
    links += `<li class="nav-item"><a href="#" id="logoutLink" class="btn btn-sm btn-outline-light ms-lg-2">Logout</a></li>`;
  } else {
    links += `<li class="nav-item"><a class="nav-link" href="${root}login.html">Login</a></li>`;
    links += `<li class="nav-item"><a class="btn btn-sm btn-light ms-lg-2" href="${root}register.html">Register</a></li>`;
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
      window.location.href = `${root}login.html`;
    });
  }
}

function showMessage(elementId, message, isError = true) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.textContent = message;
  el.className = isError ? 'message error' : 'message success';
}
