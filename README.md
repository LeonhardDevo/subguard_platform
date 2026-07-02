# SubGuard Platform

SubGuard is a robust, production-ready SaaS subscription management and billing automation platform designed for B2B tech companies and SaaS businesses. It acts as a comprehensive layer on top of payment gateways (like Stripe) to handle all complex billing logic, customer management, dunning (payment retries), and subscription analytics automatically.

## 🎯 What is it for?
Building subscription logic from scratch is notoriously difficult and error-prone. SubGuard solves this by providing a complete, secure, and highly scalable backend system that handles:
- **Complex Billing lifecycles**: Upgrades, downgrades, cancellations, and trial periods.
- **Dunning (Payment Recovery)**: Automatically retrying failed payments securely over 72 hours before canceling a subscription to minimize involuntary churn.
- **Financial Analytics**: Tracking real-time Monthly Recurring Revenue (MRR), Lifetime Value (LTV), active subscriber counts, and churn rates with pinpoint accuracy.
- **Platform Monetization**: Tracking a 1% platform commission on all successful payments.

## ⚙️ How it works
SubGuard is driven by an **Event-Driven Architecture** and robust background jobs to ensure zero data loss and maximum responsiveness (<300ms latency).

### 1. Subscription Flow
1. A Business Owner registers and connects their Stripe account.
2. They create subscription `Plans` (e.g., $29/mo Pro Plan).
3. `Customers` are imported or created via the API.
4. When a `Subscription` is created, SubGuard provisions the subscription directly in Stripe and tracks the state locally.
5. *Freemium Limits* ensure owners only manage up to 50 active subscribers before requiring a platform upgrade.

### 2. The Webhook Engine (The Heart of the System)
SubGuard relies on Stripe webhooks to act as the ultimate source of truth, ensuring the database is always perfectly synced with actual bank transactions:
- **Security First**: Every incoming webhook is cryptographically verified to prevent fake payment injections.
- **Idempotency**: Webhook events are logged to a `webhook_events` table before processing to absolutely guarantee no event is ever processed twice (preventing double-charging or duplicate metrics).
- **Processing**: When an `invoice.paid` event arrives, the system registers a `Payment`, calculates the 1% platform commission, updates the subscription's `next_billing_date` based on the exact Stripe period, and fires a `PaymentSucceeded` event.

### 3. Dunning & Automatic Retries
If a payment fails (e.g., expired card, insufficient funds):
1. A `FailedPayment` record is created and the subscription status changes to `past_due`.
2. The **Payment Retry Queue** takes over automatically via background workers.
3. The system retries the charge after 24 hours, then 48 hours, and finally 72 hours.
4. If the 3rd retry fails, SubGuard fires a `SubscriptionCanceled` event and fully cuts off access.

### 4. Financial Analytics Engine
SubGuard provides dashboard metrics that represent pure, actionable business health:
- **MRR (Monthly Recurring Revenue)**: Calculated exclusively from active, paying subscribers (strictly excluding free trialing users).
- **Churn Rate**: Calculated dynamically using the formula: `Canceled / (Active + Canceled)` for the specific reporting period.
- **LTV**: Displays the accurate Lifetime Value based on the literal sum of all successful historical payments per customer.
- **N+1 Safe Queries**: The analytics engine utilizes optimized aggregate database queries and Redis caching (5-minute TTL) to load dashboards instantly even with 100,000+ subscriber records.

## 🛠️ Technology Stack

- **Framework**: Laravel 12 (PHP 8.3)
- **Database**: PostgreSQL (Strict foreign keys and indexed columns)
- **Cache & Queues**: Redis (Handling all background workers and analytics caching)
- **Authentication**: JWT (JSON Web Tokens) with Role-Based Access Control (RBAC)
- **Payment Gateway**: Stripe API
- **Infrastructure**: Fully Dockerized (App, Nginx, Postgres, Redis, Queue Workers, Schedulers)
- **CI/CD**: GitHub Actions (Linting, PHPUnit Testing, Security Audits, Docker Image Builds)
- **Frontend**: Next.js (App Router), React, TypeScript, TailwindCSS

## 🚀 Getting Started

### Prerequisites
- Docker & Docker Compose
- Composer 
- Node.js & npm

### Installation
1. Clone the repository and install PHP dependencies:
   ```bash
   composer install
   ```
2. Copy the environment variables:
   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan jwt:secret
   ```
3. Boot the Docker infrastructure:
   ```bash
   docker-compose up -d
   ```
4. Run database migrations and seed the database:
   ```bash
   docker-compose exec app php artisan migrate
   ```
5. Install frontend dependencies and start the dev server:
   ```bash
   cd frontend
   npm install
   npm run dev
   ```

### Running Tests
The system includes a massive, comprehensive PHPUnit test suite validating all billing, API, and webhook logic.
```bash
php artisan test
```

## 🔒 Security Posture
- **Rate Limiting**: Preventative rate-limiting across all authentication and API endpoints.
- **IDOR Protection**: Strict HTTP Policies ensure Business Owners can absolutely never access subscription data belonging to other organizations.
- **Data Integrity**: Enforces strict Type Casting (Decimal for currency, UUIDs, Date objects).


## How to integrate
To connect another project (your client application) to SubGuard, you will use SubGuard's RESTful API. Your other project will act as a "Client" that sends HTTP requests to SubGuard to manage customers and subscriptions.

Here is the step-by-step API integration workflow you need to implement in your other project:

1. Authenticate (Get an API Token)
Before your project can manage subscriptions, it needs to authenticate as a registered Business Owner on SubGuard to get a JWT Token.

Request:

http
POST http://localhost:8000/api/auth/login
Content-Type: application/json
{
  "email": "owner@yourcompany.com",
  "password": "your-password"
}
Response: You will receive an access_token. You must include this token in the Authorization header for all subsequent requests: Authorization: Bearer {access_token}.

2. Create a Customer in SubGuard
When a user signs up on your other project, you need to create them as a Customer inside SubGuard so they can be billed.

Request:

http
POST http://localhost:8000/api/customers
Authorization: Bearer {access_token}
Content-Type: application/json
{
  "name": "John Doe",
  "email": "john@example.com",
  "external_id": "user_12345" // The ID from your other project's database
}
Response: SubGuard will return a Customer object with a unique SubGuard 

id
 (e.g., id: 15). Save this 

id
 in your other project to link the accounts.

3. Fetch Available Plans
When John Doe visits your pricing page, your project should fetch the available plans from SubGuard to display them dynamically.

Request:

http
GET http://localhost:8000/api/plans
Authorization: Bearer {access_token}
Response: A list of active subscription plans (e.g., Basic, Pro) including their Stripe provider_plan_id.

4. Create a Subscription
When John Doe chooses the "Pro Plan" and successfully enters his credit card on your frontend (getting a Stripe Payment Method Token), you send that securely to SubGuard to start the subscription.

Request:

http
POST http://localhost:8000/api/subscriptions
Authorization: Bearer {access_token}
Content-Type: application/json
{
  "customer_id": 15,          // The customer ID you got in Step 2
  "plan_id": 2,               // The Pro Plan ID you got in Step 3
  "payment_method_id": "pm_card_visa" // Generated securely by Stripe Elements on your frontend
}
Response: SubGuard creates the subscription in Stripe, handles the billing, and returns the active subscription details.

5. Check Subscription Status (Access Control)
Every time John Doe tries to access a premium feature in your other project, your backend should perform a quick check to ensure his subscription is 

active
.

Request:

http
GET http://localhost:8000/api/subscriptions?customer_id=15
Authorization: Bearer {access_token}
If the status is 

active
, grant access. If the status is past_due or canceled (which SubGuard will automatically handle if John's card fails 3 times), your project should deny access and prompt him to update his card!