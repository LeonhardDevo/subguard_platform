# SubGuard API Documentation

## Base URL

```
http://localhost:8000/api
```

## Authentication

All protected endpoints require a JWT token in the `Authorization` header:

```
Authorization: Bearer <token>
```

---

## Auth Endpoints

### POST /auth/register

Register a new user.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "company_name": "Acme Inc"
}
```

**Response:** `201 Created`
```json
{
  "message": "Registration successful",
  "data": {
    "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
    "token": "eyJ...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

### POST /auth/login

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response:** `200 OK` — Same structure as register.

### POST /auth/logout *(Protected)*
### POST /auth/refresh *(Protected)*
### GET /auth/me *(Protected)*
### POST /auth/forgot-password
### POST /auth/reset-password

---

## Customer Endpoints *(All Protected)*

### GET /customers

Query params: `status`, `search`, `sort_by`, `sort_dir`, `per_page`

### POST /customers

```json
{
  "name": "Jane Smith",
  "email": "jane@company.com",
  "phone": "+1234567890",
  "external_id": "ext-001",
  "status": "active"
}
```

### PUT /customers/{id}
### DELETE /customers/{id}
### POST /customers/import

Upload CSV file with columns: `name`, `email`, `phone`, `external_id`, `status`

---

## Plan Endpoints *(All Protected)*

### GET /plans

Query params: `is_active`, `search`, `sort_by`, `sort_dir`, `per_page`

### POST /plans

```json
{
  "name": "Pro Plan",
  "price": 29.99,
  "currency": "USD",
  "interval": "monthly",
  "trial_days": 14,
  "description": "Professional features",
  "features": ["Feature A", "Feature B"]
}
```

### PUT /plans/{id}
### DELETE /plans/{id}

---

## Subscription Endpoints *(All Protected)*

### GET /subscriptions

Query params: `status`, `customer_id`, `sort_by`, `sort_dir`, `per_page`

### POST /subscriptions

```json
{
  "customer_id": 1,
  "plan_id": 1
}
```

### GET /subscriptions/{id}
### PUT /subscriptions/{id}/cancel

```json
{
  "reason": "No longer needed"
}
```

---

## Invoice Endpoints *(All Protected)*

### GET /invoices
### GET /invoices/{id}
### GET /invoices/{id}/download

Returns PDF file.

---

## Analytics *(Protected)*

### GET /analytics/dashboard

Returns MRR, churn rate, LTV, active subscribers, revenue, growth data.

---

## Webhooks

### POST /webhooks/stripe

Stripe webhook endpoint. Must be configured in Stripe Dashboard.
Handles: `invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted`, `customer.subscription.updated`

---

## Health

### GET /health

Returns service health status (database, cache, storage).
