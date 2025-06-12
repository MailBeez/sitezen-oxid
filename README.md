# SiteZen Module

This module provides a webhook endpoint and allows configuration of a token for OXID eShop.

## Features

- Configuration setting to set and store a token string
- Public webhook entry point that validates the token

## Installation

1. Install the module via Composer:
   ```
   composer require oxid-sales/sitezen
   ```

2. Activate the module in the OXID admin panel:
   - Go to Extensions > Modules
   - Find "SiteZen Module" in the list
   - Click "Activate"

## Configuration

1. Go to Extensions > Modules
2. Find "SiteZen Module" in the list
3. Click on "Settings"
4. Enter your desired token in the "Webhook Token" field
5. Save the settings

## Usage

The webhook endpoint is available at:
```
https://your-shop-url/?cl=webhook&token=your-token
```

### Request Format

The webhook accepts JSON payloads. Send your data as a JSON object in the request body.

Example:
```
POST /?cl=webhook&token=your-token HTTP/1.1
Host: your-shop-url
Content-Type: application/json

{
  "event": "order_created",
  "data": {
    "order_id": "12345",
    "customer_id": "67890"
  }
}
```

### Response Format

The webhook returns JSON responses:

Success:
```json
{
  "status": "success",
  "message": "Webhook received successfully",
  "received_data": {
    "event": "order_created",
    "data": {
      "order_id": "12345",
      "customer_id": "67890"
    }
  }
}
```

Error:
```json
{
  "status": "error",
  "message": "Unauthorized"
}
```

## Security

- Always use a strong, unique token
- The webhook endpoint requires a valid token for authentication
- If no token is configured or the provided token doesn't match, the request will be rejected

## License

This module is licensed under the GNU General Public License v3.0.