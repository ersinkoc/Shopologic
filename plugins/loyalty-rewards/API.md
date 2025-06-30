# Loyalty & Rewards Program API Documentation

## Overview

Complete loyalty program with points, tiers, rewards, referrals, and VIP benefits to increase customer retention and lifetime value

## REST Endpoints

### `GET /api/v1/loyalty/balance`

Handler: `Controllers\LoyaltyController@getBalance`

Description: TODO - Add endpoint description

### `GET /api/v1/loyalty/history`

Handler: `Controllers\LoyaltyController@getHistory`

Description: TODO - Add endpoint description

### `POST /api/v1/loyalty/redeem`

Handler: `Controllers\LoyaltyController@redeemPoints`

Description: TODO - Add endpoint description

### `GET /api/v1/loyalty/rewards`

Handler: `Controllers\RewardsController@getAvailable`

Description: TODO - Add endpoint description

### `POST /api/v1/loyalty/rewards/{id}/claim`

Handler: `Controllers\RewardsController@claimReward`

Description: TODO - Add endpoint description

### `GET /api/v1/loyalty/referral/code`

Handler: `Controllers\ReferralController@getCode`

Description: TODO - Add endpoint description

### `POST /api/v1/loyalty/referral/apply`

Handler: `Controllers\ReferralController@applyCode`

Description: TODO - Add endpoint description

### `GET /api/v1/loyalty/tiers`

Handler: `Controllers\TierController@getTiers`

Description: TODO - Add endpoint description

### `POST /api/v1/loyalty/points/adjust`

Handler: `Controllers\AdminController@adjustPoints`

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
