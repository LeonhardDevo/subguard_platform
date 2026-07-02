Review `C:\Users\Asus\Documents\subguard-platform` for application security and fix all issues found.

Known areas that need fixing:
- `routes/api.php`
- `app/Http/Middleware/AdminMiddleware.php`
- `app/Http/Controllers/Api/Admin/AdminUserController.php`
- `app/Http/Controllers/Api/Admin/AdminSystemController.php`

Expected tasks:
1. Fix SQL injection risk in owner search.
2. Review password reset/auth flows for abuse.
3. Review tenant admin endpoints for overprivilege and missing scoping.
4. Review Stripe secret exposure risk and add safer handling.
5. Review queue retry path for abuse.
6. Improve logging/auditing for admin actions.
7. Remove debug/verbose leakage if present.
8. Summarize the final risk level after fixes.

This is higher priority than feature work.