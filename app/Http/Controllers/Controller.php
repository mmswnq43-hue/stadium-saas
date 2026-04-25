<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Stadium SaaS API Documentation",
 *     description="API documentation for the Stadium SaaS Backend system. This API handles stadium bookings, management, and tenant operations.",
 *     @OA\Contact(
 *         email="support@stadium-saas.com"
 *     ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8080/api",
 *     description="Local Development Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Use your Sanctum token to authenticate requests."
 * )
 */
abstract class Controller
{
    //
}
