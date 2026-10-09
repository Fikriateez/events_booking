# Event Booking Portal

A Bootstrap 5 event ticket booking frontend with a PHP/MySQL backend. This README focuses on the customer booking workflow: creating a ticket, viewing the latest booking, cancelling a ticket, and receiving email notifications.

## Features

- Bootstrap 5 booking interface.
- Event and ticket quantity selection.
- Automatic total price calculation.
- Booking creation through a REST-style `POST` request.
- Latest booking loaded from the database after refresh.
- Ticket cancellation through a REST-style `PUT` request.
- Cancelled bookings are hidden after refresh.
- Ticket stock is restored after cancellation.
- SendGrid email notifications for:
  - Booking confirmation.
  - Booking cancellation.
- Bootstrap alerts for success, validation, connection, and API errors.
- Minimalist login and logout flow.

## Project Structure

```text
events_booking/
├── backend/
│   ├── bookings.php       # Booking GET, POST, and PUT API
│   ├── db.php             # MySQL PDO connection
│   ├── login.php          # Login API
│   └── auth.php           # JWT authentication helpers
├── database/
│   └── database.sql       # Database schema and sample data
├── frond_end/
│   ├── booking.html       # Booking page
│   └── login.html         # Bootstrap 5 login page
└── vendor/
    └── composer.json      # PHP dependency definition
```

## Requirements

- XAMPP with Apache, PHP, and MySQL.
- PHP cURL extension enabled.
- A MySQL database named `event_booking_db`.
- A SendGrid account with:
  - A verified sender address.
  - A valid API key.

## Installation

1. Copy the project into:

   ```text
   C:\xampp\htdocs\events_booking
   ```

2. Start Apache and MySQL in XAMPP.

3. Import the database script:

   ```text
   database/database.sql
   ```

4. Confirm the database settings in `backend/db.php`:

   ```php
   $host = "localhost";
   $dbname = "event_booking_db";
   $username = "root";
   $password = "";
   ```

5. Configure the SendGrid API key as a server environment variable. Do not commit the key into PHP source code:

   ```apache
   SetEnv SENDGRID_API_KEY "YOUR_SENDGRID_API_KEY"
   ```

   Add the setting to XAMPP Apache configuration, restart Apache, and verify the sender address in SendGrid.

6. Open the login page:

   ```text
   http://localhost/events_booking/frond_end/login.html
   ```

## Login

The demo login credentials are:

```text
Username: admin
Password: admin123
```

After successful login, the page stores the authentication token in `sessionStorage` and redirects to `booking.html`.

The **Logout** button removes the stored session values and redirects back to `login.html`.

## Booking Workflow

### 1. Create a booking

On `booking.html`:

1. Select an event.
2. Enter the ticket quantity.
3. Confirm the calculated total.
4. Press **Confirm & Pay (POST)**.

The backend:

1. Reads the event price.
2. Creates a confirmed booking in `bookings`.
3. Decreases `available_tickets`.
4. Returns the booking ID immediately to the frontend.
5. Sends a booking confirmation email through SendGrid.

Successful response example:

```json
{
  "success": true,
  "message": "Booking successful. Confirmation email queued.",
  "booking_id": "42",
  "total_price": 50,
  "email_type": "booking_confirmation",
  "email_subject": "Ticket Booking Confirmation - #42",
  "email_sent": true
}
```

### 2. Refresh the booking

The page loads the latest booking with:

```http
GET /events_booking/backend/bookings.php?user_id=1
```

Only the newest booking is considered. If the newest booking is cancelled, older bookings do not reappear.

### 3. Cancel a booking

Press **Cancel Ticket** and confirm the booking reference. The frontend sends:

```http
PUT /events_booking/backend/bookings.php
Content-Type: application/json
```

Request body:

```json
{
  "booking_id": 42,
  "booking_status": "Cancelled"
}
```

The backend:

1. Updates the booking status to `Cancelled`.
2. Restores the ticket quantity to `events.available_tickets`.
3. Sends a cancellation email through SendGrid.
4. Shows the cancelled pass immediately.
5. Hides the cancelled pass after the next refresh.

Successful cancellation response example:

```json
{
  "success": true,
  "message": "Booking #42 updated to Cancelled.",
  "booking_id": 42,
  "email_type": "booking_cancellation",
  "email_subject": "Booking Cancellation Notice - #42",
  "email_sent": true
}
```

## Booking API

### Get the latest active booking

```http
GET /backend/bookings.php?user_id=1
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "booking_id": 42,
      "user_id": 1,
      "event_id": 1,
      "quantity": 1,
      "total_price": "50.00",
      "booking_status": "Confirmed",
      "event_name": "Konsert Live 2026"
    }
  ]
}
```

### Create a booking

```http
POST /backend/bookings.php
Content-Type: application/json
```

```json
{
  "event_id": 1,
  "quantity": 1
}
```

### Cancel a booking

```http
PUT /backend/bookings.php
Content-Type: application/json
```

```json
{
  "booking_id": 42,
  "booking_status": "Cancelled"
}
```

## Email Notifications

The email configuration is:

```text
From: fikries184@gmail.com
To: kl2508019931@student.uptm.edu.my
```

The backend reports delivery status through `email_sent`:

- `true`: SendGrid accepted the email request.
- `false`: the request failed; inspect the Apache/PHP error log.

The email categories are:

- `booking-confirmation`
- `booking-cancellation`

If an email is accepted but not visible, check the recipient's Inbox, Spam, Trash, and All Mail folders. Search by subject:

```text
Ticket Booking Confirmation
Booking Cancellation Notice
```

## Troubleshooting

### Ticket disappears after refresh

Check that the GET endpoint returns HTTP 200 and valid JSON. The endpoint must use the event column alias:

```sql
e.title AS event_name
```

The database schema uses `events.title`, not `events.event_name`.

### Email is not sent

Check the booking response:

```json
"email_sent": true
```

If it is `false`:

1. Confirm `SENDGRID_API_KEY` is available to the Apache PHP process.
2. Restart Apache after changing its configuration.
3. Confirm the sender address is verified in SendGrid.
4. Check `C:\xampp\apache\logs\error.log`.

### Cancelled ticket reappears

The GET endpoint should only return the newest booking and should exclude a cancelled newest booking. Confirm the database status is:

```text
Cancelled
```

## Security Notes

- Never commit a real SendGrid API key.
- Revoke any API key that has been exposed.
- Use an environment variable or private server configuration.
- Replace the demo login credentials before production use.
