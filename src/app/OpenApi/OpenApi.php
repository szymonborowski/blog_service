<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Blog API',
    version: '1.0.0',
    description: 'API for managing blog posts, categories, tags and comments'
)]
#[OA\Server(
    url: 'https://blog.microservices.local',
    description: 'Production'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
class OpenApi
{
}
