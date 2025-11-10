# BillingController Routing Note

SubscriptionController has been removed in favor of Billing/BillingController. The following routes are supported now:

- GET /api/subscription -> BillingController@current
- GET /api/subscription/status -> BillingController@status
- GET /api/billing/current -> BillingController@current
- GET /api/billing/history -> BillingController@history

No functional changes expected for clients.
