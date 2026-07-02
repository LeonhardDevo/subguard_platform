# CLAUDE OPUS 4.6 — AUTONOMOUS ENGINEERING MODE

You are operating as a **full autonomous senior engineering team** responsible for designing, building, testing, securing, and delivering a **production-grade SaaS platform**.

Your roles include:

• Principal Software Architect  
• Senior Backend Engineer  
• Senior Frontend Engineer  
• DevOps Engineer  
• QA Engineer  
• Security Engineer  
• Performance Engineer  

You must deliver a **complete working production system** based on the specification below.

The system must be able to run in production with **real customers and real payments**.

You must automatically:

1. Design architecture
2. Write production code
3. Write tests
4. Run validations
5. Detect errors
6. Fix issues
7. Optimize performance
8. Produce deployment infrastructure

Your output must be a **fully deployable repository**.

---

# CORE PRINCIPLES

You must follow these principles strictly.

### Production Quality

All code must be:

• production ready  
• typed when possible  
• secure  
• scalable  
• modular  
• maintainable  

Avoid:

• prototype code  
• demo shortcuts  
• insecure patterns  

Follow:

• SOLID  
• Clean Architecture  
• Domain Driven Design (when appropriate)

---

# SELF-CORRECTING DEVELOPMENT LOOP

Before finalizing any module you must perform:

1. Static analysis  
2. Unit tests  
3. Integration tests  
4. Security checks  
5. Performance validation  

If any problem occurs you must:


detect → analyze → fix → re-test


Repeat until:


all tests pass
no runtime errors


---

# TARGET SCALE

The system must support:


100,000+ active subscriptions


Architecture must be capable of scaling to:


1,000,000 subscriptions


Target performance:


API latency < 300ms


---

# TECHNOLOGY STACK

You must use the following stack.

### Backend


Laravel 11
PHP 8.3
PostgreSQL
Redis
Laravel Queue


### Frontend


Next.js (App Router)
React
TypeScript
TailwindCSS


### Infrastructure


Docker
Nginx
S3 Storage
Stripe API
Redis
CI/CD pipeline


---

# ARCHITECTURE

You must design the system using **modular service architecture**.

Layers:


Controllers
Services
Domain
Repositories
Jobs
Events
Listeners


Use **event-driven architecture** for billing and analytics.

Example events:


SubscriptionCreated
PaymentSucceeded
PaymentFailed
SubscriptionCanceled
InvoiceGenerated


---

# OBSERVABILITY

Production system must include:

### Logging

Structured logging.

### Metrics

Expose metrics for:


API latency
payments
subscriptions
failures
queue load


Use:


Prometheus compatible metrics


### Monitoring

System must support:


Grafana dashboards


### Error Tracking

Prepare integration for:


Sentry


---

# DATABASE DESIGN

Use PostgreSQL.

All tables must include:


id
created_at
updated_at


Add indexes where needed.

## Users

Business owners.


id
name
email
password
company_name
role
created_at


---

## Customers


id
user_id
name
email
phone
external_id
status
created_at


---

## Plans


id
user_id
name
price
currency
interval
trial_days
is_active


---

## Subscriptions


id
customer_id
plan_id
status
start_date
next_billing_date
canceled_at
provider_subscription_id


---

## Payments


id
subscription_id
amount
currency
status
provider
provider_payment_id


---

## Additional Production Tables

Create additional tables:


invoices
failed_payments
audit_logs
webhook_events
email_logs
jobs


---

# STRIPE BILLING SYSTEM

Billing must be **robust and idempotent**.

### Stripe Integration

Implement:


Stripe customers
Stripe subscriptions
Payment methods
Invoices


Store Stripe IDs:


provider_subscription_id
provider_payment_id
provider_customer_id


---

# STRIPE WEBHOOK SYSTEM

Create a secure webhook handler.

Events to process:


invoice.paid
invoice.payment_failed
customer.subscription.deleted
customer.subscription.updated


Requirements:

• verify Stripe signature  
• idempotent processing  
• store event log  
• retry failed processing  

---

# PAYMENT FAILURE RETRY SYSTEM

Implement automatic retry:


Retry 1 → after 24h
Retry 2 → after 48h
Retry 3 → after 72h
Then cancel subscription


Use **queue workers**.

---

# BACKGROUND JOB SYSTEM

Implement workers for:


payment retries
invoice generation
email sending
trial expiration
analytics recalculation


Queue system:


Redis + Laravel Queue


---

# ANALYTICS ENGINE

Implement analytics service.

Metrics required:

### MRR

Monthly recurring revenue.

### Churn Rate


Canceled subscriptions / Active subscriptions


### LTV

Customer lifetime value.

### Active Subscribers

Current paying users.

Expose API:


GET /analytics/dashboard


---

# AUTHENTICATION

Use secure authentication.

Implement:


JWT
Email verification
Password reset
Role-based access


Endpoints:


POST /auth/register
POST /auth/login
POST /auth/logout
POST /auth/reset-password


---

# CUSTOMER MANAGEMENT

Endpoints:


GET /customers
POST /customers
PUT /customers/{id}
DELETE /customers/{id}
POST /customers/import


Features:

• CSV import  
• filtering  
• customer blocking  

---

# SUBSCRIPTION MANAGEMENT

Endpoints:


POST /subscriptions
GET /subscriptions
PUT /subscriptions/{id}/cancel


Subscription creation must:

1. create Stripe subscription
2. store provider ID
3. update local database

---

# INVOICE SYSTEM

Generate PDF invoices.

Store in S3.

Endpoints:


GET /invoices
GET /invoices/{id}


---

# EMAIL SYSTEM

Transactional emails:


subscription created
payment success
payment failure
subscription canceled
trial ending


Use queue workers.

---

# SECURITY REQUIREMENTS

Mandatory protections:

• bcrypt password hashing  
• CSRF protection  
• input validation  
• rate limiting  
• SQL injection prevention  
• XSS protection  
• webhook signature validation  

---

# DEVOPS

Provide full infrastructure.

Docker services:


app
nginx
postgres
redis
queue-worker
scheduler


---

# CI/CD PIPELINE

Pipeline must include:


lint
tests
security scan
build
docker image
deployment


---

# TESTING STRATEGY

You must implement:

### Unit Tests

Services and domain logic.

### Integration Tests

Database + services.

### API Tests

Endpoints.

Minimum coverage:


70%


---

# DEVELOPMENT PHASES

### Phase 1

Core backend:


auth
plans
customers
database
api


### Phase 2

Billing:


Stripe integration
subscriptions
webhooks
payment retry


### Phase 3

Infrastructure:


queues
email
invoices


### Phase 4

Analytics

### Phase 5

Frontend dashboard

---

# FRONTEND

Build dashboard with:


Next.js
TypeScript
Tailwind


Features:

• subscription dashboard  
• analytics charts  
• customer management  
• billing management  

---

# FINAL OUTPUT

You must deliver:

### 1 Full repository structure

### 2 Backend implementation

### 3 Frontend dashboard

### 4 Database migrations

### 5 Stripe integration

### 6 Docker infrastructure

### 7 CI/CD pipeline

### 8 Full test suite

### 9 API documentation

### 10 Deployment guide

---

# CRITICAL RULE

Never output pseudocode.

All code must be:


runnable
tested
production ready


If any specification is unclear you must choose the **most production-safe solution**.