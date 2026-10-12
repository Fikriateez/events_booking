# Event Ticketing and Venue Booking System

## Project Overview

The Event Ticketing and Venue Booking System is a web-based application developed
using a RESTful API architecture. The system allows administrators and organisers
to manage events, venues, schedules, and ticket availability. Customers can browse
events, make bookings, cancel bookings, and view their booking status.

This project uses PHP for backend API development, MySQL for database management,
HTML/CSS/JavaScript for frontend development, Bootstrap 5 for the user interface,
and JWT for authentication.

## Technologies Used

- PHP
- MySQL
- HTML
- CSS
- JavaScript
- Bootstrap 5
- RESTful API
- JSON Web Token (JWT)
- SendGrid API
- XAMPP

## Main Features

- User login and logout.
- Event and ticket quantity selection.
- Automatic total price calculation.
- Booking creation through a RESTful `POST` request.
- Immediate booking confirmation after pressing **Confirm & Pay**.
- Latest booking loaded from the database after refreshing the page.
- Booking cancellation through a RESTful `PUT` request.
- Cancelled bookings remain cancelled after refreshing the page.
- Ticket stock restoration after cancellation.
- Booking confirmation email.
- Booking cancellation email.
- Bootstrap 5 alerts and responsive layouts.
- Administrator venue management with search, filtering, pagination, and CRUD operations.

## Role Permissions

### Administrator

- View and manage users.
- View and manage venues.
- View system events.
- View system booking records.
- Oversee system records according to administrator permissions.
- Administrators can view events but cannot create, edit, or delete events.

### Organiser / Staff

- Cannot manage users.
- Cannot manage venues.
- Create events.
- Edit and manage permitted events.
- Manage event schedules.
- Manage ticket availability.
- View relevant booking records.
- Organisers can manage their own events; Staff can manage all events.

When an event is created, its `organiser_id` is taken from the authenticated
user. Venues assigned to active events are marked `In Use` automatically and
are released when the event is cancelled, completed, or deleted.

### Customer

- Cannot manage users, venues, or events.
- Browse published events.
- Check ticket availability.
- Make bookings.
- Cancel eligible own bookings.
- View own booking status and details.

## Events Permissions

| Role | View events | Create events | Edit/manage events | Delete events |
| --- | --- | --- | --- | --- |
| Administrator | Yes, all events | No | No | No |
| Organiser / Staff | Yes | Yes | Yes, within permitted scope | Yes, within permitted scope |
| Customer | Yes, published events only | No | No | No |

The backend applies these permissions through JWT authentication and role
checks:

- `GET /backend/events.php` is available to all authenticated roles.
- Customers receive only events with `published` status.
- `POST`, `PUT`, and `DELETE /backend/events.php` are available only to
  Organiser and Staff users.
- Organisers can modify only events assigned to their account.
- Staff can manage all events.

## Booking Record Permissions

| Role | Booking records visible |
| --- | --- |
| Administrator | All system booking records |
| Organiser / Staff | Relevant bookings for permitted events |
| Customer | Own bookings only |

The booking API therefore keeps the Administrator booking link and oversight
view. Administrators can use:

```http
GET /backend/bookings.php
```

Organisers receive bookings for events assigned to them, while Staff receive
all booking records. Customers receive only records belonging to their own
account.

### Venue API

The venue resource is available at `/backend/venues.php`. All authenticated
roles can list and view venues. Only Administrators can create, update, or
delete venues. The API validates venue name, location, capacity, and status,
and prevents deletion when events are still assigned to a venue. The
Bootstrap frontend is available at `frond_end/venues.html`.

## Project Structure

```text
events_booking/
├── backend/
│   ├── auth.php           # JWT authentication helpers
│   ├── bookings.php       # Booking GET, POST, and PUT API
│   ├── db.php             # MySQL PDO connection
│   ├── events.php         # Event API
│   └── login.php          # Login API
│   └── users.php          # User listing and administration API
├── database/
│   └── database.sql       # Database schema and sample data
├── frond_end/
│   ├── booking.html       # Customer booking page
│   ├── dashboard.html     # Organiser, staff, and administrator dashboard
├── admin-dashboard.html # Administrator-only oversight dashboard
│   ├── events.html        # Bootstrap event management page
│   ├── login.html         # Login page
│   ├── venues.html        # Bootstrap venue management page
│   └── user.html          # Account and administrator user-management page
├── vendor/
│   ├── composer.json      # PHP dependency definition
│   └── composer.lock      # Locked dependency versions
└── README.md
```

## Requirements

- XAMPP with Apache, PHP, and MySQL.
- PHP cURL extension enabled.
- A MySQL database named `event_booking_db`.
- A SendGrid account with a verified sender address.
- A SendGrid API key configured on the server.

## Installation

1. Copy the project into:

   ```text
   C:\xampp\htdocs\events_booking
   ```

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

3. Open phpMyAdmin and import:

   ```text
   database/database.sql
   ```

4. Check the database connection in `backend/db.php`:

   ```php
   $host = "localhost";
   $dbname = "event_booking_db";
   $username = "root";
   $password = "";
   ```

5. Configure the SendGrid API key as an Apache environment variable. Do not
   place the real key inside PHP source code or commit it to Git:

   ```apache
   SetEnv SENDGRID_API_KEY "YOUR_SENDGRID_API_KEY"
   ```

   Restart Apache after changing the configuration.

6. Open the login page:

   ```text
   http://localhost/events_booking/frond_end/login.html
   ```

## Login

Any user in the exported `users` table can sign in.
For example:

```text
Email: kl2508019931@student.uptm.edu.my
Password: Fikri123
```

After a successful login, the authentication token is stored in
`sessionStorage` and the user is redirected to the booking page.

Customers are redirected to the booking page; organiser, staff, and administrator
accounts are redirected to the management dashboard. The login token contains
the authenticated user's ID, email, and role.
Booking requests use that token instead of accepting a user ID from the browser.
The login token does not expire by time. The browser remains authenticated until
the user logs out; the Logout button clears the stored session values.

The **Logout** button redirects to the login page.

## Booking Workflow

### 1. Create a booking

1. Log in to the system.
2. Select an available event.
3. Enter the required ticket quantity.
4. Check the calculated total price.
5. Press **Confirm & Pay**.

The backend then:

1. Checks event availability.
2. Creates a confirmed booking.
3. Decreases the available ticket count.
4. Returns the booking immediately to the frontend.
5. Sends a booking confirmation email through SendGrid.

### 2. View a booking after refresh

The booking page requests the latest booking from the database:

```http
GET /events_booking/backend/bookings.php
```

The latest booking is loaded from persistent database data. A cancelled booking
does not become active again after the page is refreshed.

### 3. Cancel a booking

Press **Cancel Ticket** and confirm the cancellation. The frontend sends:

```http
PUT /events_booking/backend/bookings.php
Content-Type: application/json
```

```json
{
  "booking_id": 42,
  "booking_status": "Cancelled"
}
```

The backend then:

1. Updates the booking status to `Cancelled`.
2. Restores the ticket quantity to the event.
3. Sends a cancellation email through SendGrid.
4. Updates the booking display.
5. Keeps the booking cancelled after refresh.

## Booking API

### Get the latest booking

```http
GET /backend/bookings.php?page=1&limit=10&search=concert&status=Confirmed
```

All booking requests must include the token returned by the login API:

```http
Authorization: Bearer <token>
```

The booking confirmation and cancellation emails are sent to the email
address of the authenticated user loaded from `users.email`. For example,
logging in as `kl2508019931@student.uptm.edu.my` sends notifications to that
address.

For customers, the response includes the customer's booking history. Organisers
see bookings for events they own, while staff and administrators can monitor all
bookings. Every response also includes pagination metadata:

```json
{
  "success": true,
  "data": [],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 0,
    "totalPages": 0,
    "search": "concert",
    "status": "Confirmed"
  }
}
```

Customers can search their own bookings by event title or booking number and
filter by `Pending`, `Confirmed`, or `Cancelled` status. Results are paginated
with a maximum page size of 50.

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

The system sends two types of email:

- **Booking confirmation** after a successful booking.
- **Booking cancellation** after a successful cancellation.

The API response includes an `email_sent` value:

```json
{
  "success": true,
  "email_sent": true
}
```

`email_sent: true` means that SendGrid accepted the email request. It does not
guarantee that the recipient's mail provider placed the email in the Inbox.
If an email is missing, check Spam, Trash, All Mail, and the SendGrid activity
log.

## Troubleshooting

### The booking disappears after refresh

Check that:

- Apache and MySQL are running.
- The database was imported correctly.
- The GET request returns HTTP 200.
- The event query uses `e.title AS event_name`.

### The cancellation request fails

Check that:

- The booking ID exists.
- The request uses `PUT`.
- The request body is valid JSON.
- The booking belongs to the logged-in user.
- The database connection in `backend/db.php` is working.

### Email is not received

Check that:

1. `SENDGRID_API_KEY` is available to Apache.
2. Apache was restarted after configuration changes.
3. The sender address is verified in SendGrid.
4. The API response contains `"email_sent": true`.
5. The Apache error log is available at:

   ```text
   C:\xampp\apache\logs\error.log
   ```

## Security Notes

- Never commit a SendGrid API key.
- Revoke and replace any key that has been exposed.
- Store secrets in environment variables or private server configuration.
- Replace the demo credentials before production use.
- Do not expose database passwords or JWT secrets in public repositories.
