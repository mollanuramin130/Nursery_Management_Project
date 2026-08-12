# GreenLeaf Nursery API

Laravel modular monolith. Base path: `/api/v1`.

## Local

```bash
cd apps/nursery-api
cp .env.example .env   # if needed
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve
```

API: `http://127.0.0.1:8000/api/v1`

## Postman / OpenAPI

- Import `postman/Nursery-API.postman_collection.json`
- Import `postman/Nursery-Local.postman_environment.json`
- Contract stub: `openapi.yaml`
- Full endpoint specs: `../../docs/PROJECT_DEVELOPMENT_GUIDE.md` §9

## Envelope

All responses use `{ success, message, data, errors, meta }`.
