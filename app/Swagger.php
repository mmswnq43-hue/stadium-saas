<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Stadium SaaS API Documentation",
    description: "API documentation for the Stadium SaaS Backend system. This API handles stadium bookings, management, and tenant operations."
)]
#[OA\Server(
    url: "http://localhost:8080/api",
    description: "Local Development Server"
)]
#[OA\Contact(
    email: "support@stadium-saas.com"
)]
class Swagger {}
