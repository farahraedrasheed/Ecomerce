# ShopHub — E-commerce Platform
## Capstone Project — Laravel API + Vanilla JS Frontend

---

# Project Idea

- A full-featured e-commerce platform called ShopHub
- Decoupled architecture: REST API backend fully separated from the frontend
- Backend: Laravel 13 + Laravel Sanctum + MySQL
- Frontend: plain HTML/CSS/JavaScript, no framework, no build step
- All communication between the two sides happens via fetch() and JSON only

---

# Why This Tech Stack?

- **Laravel**: industry-standard PHP framework, MVC structure out of the box, Eloquent ORM simplifies database work
- **Laravel Sanctum**: lightweight Bearer-token authentication, purpose-built for a frontend decoupled from the backend — much simpler than a full OAuth2 setup (Passport) since there's only one first-party client talking to the API
- **MySQL**: relational database that fits the clear relationships between products, orders, and users
- **HTML/CSS/JS with no framework**: simplicity, no build tools, focus on Fetch API fundamentals and direct DOM manipulation

---

# Architecture

- HTTP request from the frontend
- → Routes (map the URL to a controller)
- → Middleware (authentication via auth:sanctum / authorization via admin)
- → Controller (validate input, run business logic)
- → Model / Eloquent (talk to the database)
- → MySQL
- → JSON response back to the frontend

No Service layer or separate Repositories — controllers directly, since the project's size doesn't justify the extra abstraction

---

# Authentication & Authorization

- Register / login / logout powered by Laravel Sanctum
- Every user has a role: customer or admin
- auth:sanctum middleware answers "who are you?"
- Custom admin middleware (EnsureUserIsAdmin) answers "are you allowed?"
- Additional ownership checks inside the controllers themselves (e.g. you can only edit your own product, or view your own orders)

---

# Product & Category Management

- Public product browsing: filter by category, search by name
- Full CRUD for categories (admin only)
- Prevents deleting a category that still has products attached to it
- Every product belongs to one category and has an image, price, stock, and active status

---

# Shopping Cart

- Every user has their own personal cart
- Adding a product: increases quantity if it's already in the cart, otherwise creates a new line item
- Stock availability is checked before adding
- Cart total is always calculated on the backend, never trusted from the frontend (prevents tampering)
- Ownership check enforced on every update/delete operation

---

# Mock Payment Gateway

- A realistic simulated payment flow — no real bank integration (safe for a student project)
- Card number validated using the real Luhn algorithm (the same one Visa/Mastercard actually use)
- Card expiry date validation
- Success test card: 4242 4242 4242 4242
- Decline test card: 4000 0000 0000 0002 (simulates a bank rejecting the transaction)
- Only the last 4 digits of the card are ever stored — the full card number and CVV are never persisted (a real PCI-DSS security principle applied in practice)

---

# Orders

- Order creation from the cart happens inside a single DB Transaction
- If any step fails, all changes automatically roll back
- Stock is automatically decremented once payment succeeds
- Order status tracking: pending → processing → shipped → delivered (or cancelled)
- Customers see only their own orders; admins see all orders
- Only admins can update order status

---

# "Sell Your Product" Marketplace Feature

- Any logged-in user (not just admins) can list a product they want to sell
- The listing goes live immediately, with no admin review
- The product is automatically linked to the seller's account (user_id comes from the auth token, never from the form, to prevent spoofing)
- Editing and deleting are restricted to the product's owner or an admin
- A "Sell a Product" page lets users manage their own listings (My Listings)
- The seller's name is displayed on the product detail page

---

# My Account Page

- View account info: name, email, role, member-since date
- Save a payment card to the account for later viewing (masked: Visa •••• 4242)
- Automatic card brand detection (Visa / Mastercard / Amex)
- Ability to remove the saved card
- Card validation logic reused from a single shared class (CardValidator) to avoid duplicating code

---

# Admin Dashboard

- Full statistics: total revenue, total orders, total products, total customers
- Daily revenue for the last 14 days
- Orders broken down by status
- Top 5 best-selling products
- Full management: products, categories, orders

---

# Security Practices

- Full card numbers and CVVs are never stored in the database
- Passwords are automatically hashed (bcrypt)
- CORS configured properly, with authentication via Bearer tokens rather than cookies
- Strict ownership checks on every personal resource (cart, orders, products)
- Generic, unified error messages on failed login (never reveals whether the email or the password was wrong)
- Custom middleware to enforce admin-only permissions

---

# Testing

- 45 feature tests covering every resource
- AuthTest, ProductTest, CategoryTest, CartTest, OrderTest, AccountTest
- Every test runs against a freshly reset database (RefreshDatabase)
- Comprehensive coverage: registration and login, admin vs. customer permissions, ownership checks, successful and declined payment scenarios
- All tests passing at 100%

---

# Key Architectural Decisions

- Fat Controllers instead of a Service layer: the project's scope doesn't justify extra abstraction
- Sanctum instead of Passport: only one first-party client, no need for full OAuth2
- DB Transactions on orders: guarantees data consistency
- Shared CardValidator class: avoids duplicating card-validation logic across two places
- Ownership checks inside controllers instead of separate Policy classes: simpler and clearer at this project's scale
- Storing only the last 4 card digits: a real security principle, not just for show

---

# Summary

- A fully functional e-commerce platform: browsing, purchasing, payment, order tracking
- An added marketplace twist: any user can sell their own products
- Personal account management with a saved payment card
- A comprehensive admin dashboard with statistics
- Secure, fully tested, and built on Laravel best practices
