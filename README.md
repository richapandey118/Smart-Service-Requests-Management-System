# Smart Service Requests Management System

## Project Overview
This project is designed to streamline the management of service requests, allowing users to submit, track, and manage their requests effectively. It incorporates various functionalities that enhance user experience and administrative control.

## Features
- User-friendly interface for submitting service requests
- Tracking of request status
- Administrative dashboards for managing requests
- Notifications for request updates
- User authentication and authorization

## Database Schema
The database schema consists of the following main collections:
1. **Users**: Stores user information such as username, password, and role.
2. **Requests**: Contains information about the service requests, including status and timestamps.
3. **Admins**: Manages admin credentials and permissions.

## API Endpoints
- `GET /api/requests`: Retrieve all service requests.
- `POST /api/requests`: Submit a new service request.
- `PUT /api/requests/:id`: Update an existing service request.
- `DELETE /api/requests/:id`: Delete a service request.
- `GET /api/users`: Retrieve user details.

For more details, refer to the API documentation.
