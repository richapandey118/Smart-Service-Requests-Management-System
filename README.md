# Smart Service Requests Management System

## Project Overview
This project is designed to streamline the management of service requests, allowing users to submit, track, and manage their requests effectively. It incorporates various functionalities that enhance user experience and administrative control.

## Features
- User-friendly interface for submitting service requests
- Tracking of request status
- Administrative dashboards for managing requests
- Notifications for request updates
- User authentication and authorization

## Installation
To install this project, follow these steps:
1. Clone the repository:
   ```bash
   git clone https://github.com/richapandey118/Smart-Service-Requests-Management-System.git
   ```
2. Navigate to the project directory:
   ```bash
   cd Smart-Service-Requests-Management-System
   ```
3. Install dependencies:
   ```bash
   npm install
   ```
4. Configure the environment variables in a `.env` file.
5. Run the application:
   ```bash
   npm start
   ```

## Usage
Once the application is running, users can access it via `http://localhost:3000`. Follow the prompts to create and manage service requests. Administrators can access administrative features through the admin dashboard.

## Project Structure
```
Smart-Service-Requests-Management-System/
├── src/
│   ├── controllers/
│   ├── models/
│   ├── routes/
│   ├── services/
│   └── app.js
├── config/
├── public/
├── views/
├── .env
└── package.json
```

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