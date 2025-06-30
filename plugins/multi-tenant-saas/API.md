# multi-tenant-saas API Documentation

## Overview

Complete multi-tenant SaaS management system with tenant isolation, subscription billing, resource quotas, custom domains, automated provisioning, and usage analytics

## REST Endpoints

### `GET /api/v1/saas/tenants`

Handler: `Controllers\TenantController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/tenants`

Handler: `Controllers\TenantController@create`

Description: TODO - Add endpoint description

### `GET /api/v1/saas/tenants/{id}`

Handler: `Controllers\TenantController@show`

Description: TODO - Add endpoint description

### `PUT /api/v1/saas/tenants/{id}`

Handler: `Controllers\TenantController@update`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/tenants/{id}/suspend`

Handler: `Controllers\TenantController@suspend`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/tenants/{id}/activate`

Handler: `Controllers\TenantController@activate`

Description: TODO - Add endpoint description

### `GET /api/v1/saas/plans`

Handler: `Controllers\PlanController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/plans`

Handler: `Controllers\PlanController@create`

Description: TODO - Add endpoint description

### `PUT /api/v1/saas/plans/{id}`

Handler: `Controllers\PlanController@update`

Description: TODO - Add endpoint description

### `GET /api/v1/saas/subscriptions`

Handler: `Controllers\SubscriptionController@index`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/subscriptions`

Handler: `Controllers\SubscriptionController@create`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/subscriptions/{id}/upgrade`

Handler: `Controllers\SubscriptionController@upgrade`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/subscriptions/{id}/downgrade`

Handler: `Controllers\SubscriptionController@downgrade`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/subscriptions/{id}/cancel`

Handler: `Controllers\SubscriptionController@cancel`

Description: TODO - Add endpoint description

### `GET /api/v1/saas/usage`

Handler: `Controllers\UsageController@current`

Description: TODO - Add endpoint description

### `GET /api/v1/saas/usage/history`

Handler: `Controllers\UsageController@history`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/domains`

Handler: `Controllers\DomainController@add`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/domains/{domain}/verify`

Handler: `Controllers\DomainController@verify`

Description: TODO - Add endpoint description

### `GET /api/v1/saas/billing/invoices`

Handler: `Controllers\BillingController@invoices`

Description: TODO - Add endpoint description

### `POST /api/v1/saas/billing/payment-method`

Handler: `Controllers\BillingController@updatePaymentMethod`

Description: TODO - Add endpoint description

### `GET /api/v1/saas/analytics`

Handler: `Controllers\AnalyticsController@overview`

Description: TODO - Add endpoint description

## Authentication

All endpoints require proper authentication.

## Error Responses

Standard error response format:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Error description"
  }
}
```
