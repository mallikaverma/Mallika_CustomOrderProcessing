# Mallika Custom Order Processing Module

This Magento 2 module (`Mallika_CustomOrderProcessing`) provides a custom API to update order statuses, logs status changes in a custom database table, and sends email notifications for orders marked as `shipped`. The module is designed to be compatible with the Hyva theme and follows Magento 2 best practices for extensibility and performance.

## Installation

### Prerequisites
- Magento 2.4.x or higher
- PHP 8.x
- DDEV or similar local development environment (optional but recommended)
- Access to Magento Admin and a valid API token for testing

### Steps
1. **Place the Module**
   - Copy the `Mallika_CustomOrderProcessing` folder to `app/code/Mallika/CustomOrderProcessing` in your Magento installation.

2. **Register and Install**
   - Run the following commands in your DDEV environment (or directly in the Magento root):
     ```bash
     ddev exec php bin/magento setup:upgrade
     ddev exec php bin/magento setup:di:compile
     ddev exec php bin/magento cache:clean
     ```

3. **Verify Installation**
   - Confirm the module is registered:
     ```sql
     SELECT * FROM setup_module WHERE module = 'Mallika_CustomOrderProcessing';
     ```
   - Ensure the `mallika_order_status_log` table exists:
     ```sql
     DESCRIBE mallika_order_status_log;
     ```

## Usage

### API Endpoint
The module provides a REST API to update order statuses by order increment ID.

- **Endpoint**: `POST /rest/V1/orders/status`
- **Payload**:
  ```json
  {
      "orderIncrementId": "000000123",
      "status": "shipped"
  }
  ```
- **Headers**:
  ```
  Authorization: Bearer <your-api-token>
  Content-Type: application/json
  ```
- **Authentication**: Requires a valid Magento API token with `Mallika_CustomOrderProcessing::order_status` resource access.
- **Testing with Postman**:
  - Set the URL to `http://<your-magento-url>/rest/V1/orders/status`.
  - Use the above payload and headers.
  - Verify the response is `true` and check the `sales_order` table for the updated status.

### Observer
- **Event**: Listens to `sales_order_save_after` to detect order status changes.
- **Logging**: Records changes (order ID, old status, new status, timestamp) in the `mallika_order_status_log` table.
  - Verify logs:
    ```sql
    SELECT * FROM mallika_order_status_log WHERE order_id = (SELECT entity_id FROM sales_order WHERE increment_id = '000000123');
    ```
- **Email Notification**: Sends a Hyva-compatible email to the customer when the status changes to `shipped`.
  - Check email in the customer’s inbox or `var/log/mail.log` (if enabled).

### Running Tests
1. **Unit Tests**
   - Run:
     ```bash
     ddev exec vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Mallika/CustomOrderProcessing/Test/Unit
     ```
   - Tests cover the API, status management, and observer functionality.

2. **Integration Tests**
   - Configure the test database (see `dev/tests/integration/etc/install-config-mysql.php`).
   - Run:
     ```bash
     ddev exec vendor/bin/phpunit -c dev/tests/integration/phpunit.xml.dist app/code/Mallika/CustomOrderProcessing/Test/Integration
     ```

## Architectural Decisions

### API Design
- **REST API**: A custom endpoint (`/V1/orders/status`) was implemented using Magento’s Web API framework for updating order statuses by increment ID, which is customer-friendly and aligns with Magento’s order management.
- **Order Increment ID**: Used instead of entity ID to match real-world usage, leveraging `SearchCriteriaBuilder` for efficient order lookup.
- **Security**: Secured with Magento’s ACL (`Mallika_CustomOrderProcessing::order_status`) and Bearer token authentication.

### Status Management
- **Dynamic Status Retrieval**: Uses `Magento\Sales\Model\ResourceModel\Order\Status\Collection` to fetch allowed statuses and their states from `sales_order_status` and `sales_order_status_state`, supporting both default and custom statuses.
- **Fallback State**: Defaults to `Order::STATE_PROCESSING` if no state is mapped, ensuring robustness.
- **Data Patch**: A setup patch (`AddShippedStatus`) ensures the `shipped` status is available, avoiding invalid status errors.

### Observer
- **Event**: Listens to `sales_order_save_after` to capture all status changes, whether via API or admin panel.
- **Logging**: Stores status changes in a custom `mallika_order_status_log` table for auditability, using a dedicated model and resource model for clean data access.
- **Email Notification**: Sends emails only for `shipped` status using `TransportBuilder`, with a Hyva-compatible template for minimal styling and compatibility.

### Email Template
- **Hyva Compatibility**: The `order_shipped.html` template uses simple HTML/CSS to ensure compatibility with Hyva’s lightweight frontend.
- **Simplified Variables**: Uses direct variables (`order_id`, `customer_name`) instead of object methods (`order.getId()`) to avoid rendering issues in Magento’s email system.
- **Fallback**: Handles guest checkouts by defaulting `customer_name` to `'Customer'`.

### Error Handling
- **Exceptions**: Uses `NoSuchEntityException` for invalid orders and `StateException` for invalid statuses, with detailed error messages listing allowed statuses.
- **Logging**: Leverages `Psr\Log\LoggerInterface` to log errors with context (e.g., order ID, status), aiding debugging.

### Testing
- **Unit Tests**: Cover the `OrderStatusManagement` model and `OrderStatusChange` observer, testing valid/invalid inputs and edge cases (e.g., no status change, guest orders).
- **Integration Tests**: Verify API functionality and database interactions in a real Magento environment.

### Performance
- **Efficient Queries**: Uses Magento’s repository and collection patterns to minimize database queries.
- **Caching**: Leverages Magento’s caching for status collections to reduce overhead.

## Troubleshooting
- **API Errors**: Check `var/log/exception.log` or `var/log/system.log` for detailed errors. Ensure the `shipped` status exists:
  ```sql
  SELECT * FROM sales_order_status WHERE status = 'shipped';
  ```
- **Email Issues**: Enable email logging (`System > Configuration > Advanced > System > Mail Sending Settings`) and check `var/log/mail.log`.
- **Class Not Found**: Verify file paths and run `setup:di:compile` if the observer class is missing.