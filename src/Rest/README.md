# REST

This directory contains Beacon's WordPress REST API endpoints.

Rest/
RestService.php
Controllers/


## Structure

- `RestService`  
  Registers all REST controllers via `rest_api_init`.

- `Controllers/`  
  Each controller registers its own routes and handles the request.

## Current Endpoints

- `GET /beacon/v1/status`  
  Simple health check.

- `POST /beacon/v1/webhook`  
  Receives webhook events (signature verification to be added).

## Notes

- Routes are namespaced under `beacon/v1`.
- Controllers should remain thin and delegate logic to Services or Systems.
- Avoid placing business logic directly inside controllers.