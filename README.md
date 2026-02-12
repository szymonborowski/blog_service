# Blog Service

RESTful API microservice for managing blog content — posts, categories, tags, and comments. Provides both public and authenticated endpoints with JWT-based authorization. Consumes user events from RabbitMQ to keep local author data in sync.

## Architecture

```
Frontend / Admin ──▶ Traefik ──▶ Nginx ──▶ PHP-FPM (Laravel)
                                               │
                                          ┌────┴────┐
                                          ▼         ▼
                                       MySQL    RabbitMQ
                                              (user events consumer)
```

**Domain:** `blog.microservices.local`

## Tech Stack

- **Backend:** PHP 8.2 / Laravel 12
- **Database:** MySQL 8
- **Auth:** Stateless JWT (custom guard)
- **Message queue:** RabbitMQ (php-amqplib)
- **API docs:** OpenAPI 3.0 (L5-Swagger)

## API Endpoints

### Public (no auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/public/posts` | Published posts |
| GET | `/v1/public/comments` | Approved comments |
| GET | `/v1/posts` | All posts (with filters) |
| GET | `/v1/posts/{post}` | Single post |
| GET | `/v1/categories` | List categories |
| GET | `/v1/categories/{category}` | Single category |
| GET | `/v1/tags` | List tags |
| GET | `/v1/tags/{tag}` | Single tag |
| GET | `/v1/comments` | List comments |
| GET | `/v1/comments/{comment}` | Single comment |

### Protected (auth:api — JWT)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/v1/posts` | Create post |
| PUT/PATCH | `/v1/posts/{post}` | Update post |
| DELETE | `/v1/posts/{post}` | Delete post |
| POST | `/v1/categories` | Create category |
| PUT/PATCH | `/v1/categories/{category}` | Update category |
| DELETE | `/v1/categories/{category}` | Delete category |
| POST | `/v1/tags` | Create tag |
| PUT/PATCH | `/v1/tags/{tag}` | Update tag |
| DELETE | `/v1/tags/{tag}` | Delete tag |
| POST | `/v1/comments` | Create comment |
| PUT/PATCH | `/v1/comments/{comment}` | Update comment |
| DELETE | `/v1/comments/{comment}` | Delete comment |
| PATCH | `/v1/comments/{comment}/approve` | Approve comment |
| PATCH | `/v1/comments/{comment}/reject` | Reject comment |

### Health (Kubernetes probes)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Liveness probe |
| GET | `/ready` | Readiness probe (DB + RabbitMQ) |

## RabbitMQ Consumer

The `blog-consumer` container runs `php artisan rabbitmq:consume-users` to process user events:

- `user.created` — creates a local author record
- `user.updated` — updates author data

## Getting Started

### Prerequisites

- Docker & Docker Compose
- Running infrastructure services (Traefik, RabbitMQ)

### Development

```bash
cp src/.env.example src/.env
# Edit .env with your configuration

docker compose up -d
```

Containers:

| Container | Role | Port |
|-----------|------|------|
| `blog-app` | PHP-FPM | 9000 (internal) |
| `blog-nginx` | Web server | via Traefik |
| `blog-consumer` | RabbitMQ consumer | — |
| `blog-db` | MySQL 8 | 127.0.0.1:3308 |

### Running Tests

```bash
docker compose run --rm --no-deps \
  -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  blog-app ./vendor/bin/phpunit
```

### API Documentation

Swagger UI available at `blog-swagger.microservices.local` (when Traefik is running).

## Test Coverage

| Metric | Value |
|--------|-------|
| Line coverage | 80.1% |
| Tests | 98 |

## Roadmap

- [x] Full CRUD API (posts, categories, tags, comments)
- [x] JWT authentication (custom guard)
- [x] RabbitMQ consumer for user event sync
- [x] Comment moderation (approve/reject)
- [x] OpenAPI/Swagger documentation
- [x] Kubernetes manifests and health endpoints
- [ ] Tests for ConsumeUserEvents command

## License

All rights reserved.
