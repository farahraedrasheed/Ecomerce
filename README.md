# Full Stack E-Commerce Platform

A Full Stack e-commerce platform where customers can browse products, search by category, manage a shopping cart, and place orders. Admins can manage the product catalog and track/update incoming orders.

Built API-first: a Laravel backend exposes a RESTful JSON API (auth via Laravel Sanctum), consumed by a plain HTML/CSS/JavaScript frontend — no frontend framework, no build step.

**Prepared by:** Farah Raed Rasheed — Shifra Center

## Project Structure

```
Ecomerce/
├── backend/     Laravel API (PHP, MySQL, Sanctum)
└── frontend/    Plain HTML/CSS/JS pages that consume the API
```

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript (fetch API, no framework) |
| Backend | PHP / Laravel (MVC), Laravel Sanctum (token auth) |
| Database | MySQL |
| Project Management | Trello (Agile: Backlog, To-Do, In Progress, Testing/Review, Done) |

## Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- MySQL

### 1. Backend setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Set your database credentials in `backend/.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), then:

```bash
php artisan migrate --seed
php artisan serve
```

This starts the API at `http://127.0.0.1:8000`. If port 8000 is already in use, run `php artisan serve --port=8001` (or any free port) instead.

### 2. Frontend setup

The frontend needs no build step — it's served as static files.

```bash
cd frontend
php -S localhost:5500
```

Open `http://localhost:5500/index.html` in your browser.

**Important:** if you changed the backend port above, update `API_BASE` at the top of [`frontend/js/api.js`](frontend/js/api.js) to match (e.g. `http://127.0.0.1:8001/api`).

### Demo accounts

Seeded automatically by `php artisan migrate --seed`:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Customer | `customer@example.com` | `password` |

## Features

- Customer: register/login, browse & search products, filter by category, manage cart, checkout, view order history
- Admin: create/edit/deactivate/delete products, view all orders, update order status

## API Overview

All endpoints are prefixed with `/api`.

| Resource | Endpoints |
|---|---|
| Auth | `POST /register`, `POST /login`, `POST /logout` |
| Products | `GET /products`, `GET /products/{id}`, `POST /products` (admin), `PUT /products/{id}` (admin), `DELETE /products/{id}` (admin) |
| Cart | `GET /cart`, `POST /cart`, `PUT /cart/{id}`, `DELETE /cart/{id}` |
| Orders | `GET /orders`, `POST /orders`, `GET /orders/{id}`, `PUT /orders/{id}/status` (admin) |

## Running Tests

```bash
cd backend
php artisan test
```

> Only the default example tests exist so far — real feature tests for the API are not yet written.
