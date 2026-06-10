# Laravel Backend Development Guidelines

This document defines the backend development conventions for the Laravel project. The goal is to maintain a clean, readable, and maintainable codebase while keeping the business flow easy to understand.

---

# 1. General Principles

The backend should follow these principles:

* Keep the code simple and readable.
* Prefer convention over unnecessary abstraction.
* Maintain a **Thin Controller** architecture.
* Separate responsibilities into dedicated classes.
* Avoid over-engineering and excessive service decomposition.
* Make the request flow understandable by reading the controller.

---

# 2. Model Generation

When creating a new model, always use:

```bash
php artisan make:model ModelName --all
```

This command automatically generates:

* Model
* Migration
* Factory
* Seeder
* Controller
* Form Requests
* Policy

This ensures a consistent project structure.

Example:

```bash
php artisan make:model Product --all
```

---

# 3. Thin Controller Principle

Controllers should only coordinate the request lifecycle.

A controller should:

* Receive the HTTP request.
* Call validation through Form Request.
* Invoke the appropriate Service.
* Return the response.

Controllers should NOT:

* Contain complex business logic.
* Perform extensive data transformations.
* Handle file processing directly.
* Include large helper functions.

Example:

```php
public function store(StoreProductRequest $request)
{
    $product = $this->productService->store(
        $request->validated()
    );

    return response()->json($product);
}
```

A controller should clearly communicate the application's workflow.

---

# 4. Form Request Validation

All input validation must use Laravel Form Requests.

Do NOT write validation rules inside controllers.

Good:

```
app/Http/Requests/
    StoreProductRequest.php
    UpdateProductRequest.php
```

Use:

```php
$request->validated();
```

Never use:

```php
$request->all();
```

unless absolutely necessary.

---

# 5. Service Layer

Business logic should be extracted into Services.

Example:

```
app/Services/

    ProductService.php
    UserService.php
    OrderService.php
```

Services should:

* Handle business rules.
* Manage database operations.
* Coordinate multiple models.
* Process transactions.

Avoid creating deeply nested services such as:

```
ProductService
    ProductCalculationService
        ProductPriceService
            ProductDiscountService
```

Too many service layers make the code difficult to trace.

A single well-designed service is preferred over excessive abstraction.

---

# 6. Helper Classes

Reusable utility functions should be placed in Helper classes.

Examples:

```
app/Helpers/

    FileHelper.php
    StringHelper.php
    ImageHelper.php
```

Helpers should:

* Be stateless.
* Perform utility operations.
* Avoid business logic.

Business rules belong in Services, not Helpers.

---

# 7. Model Responsibilities

Models should:

* Define relationships.
* Define casts.
* Define accessors and mutators.
* Define query scopes.

Models should NOT:

* Contain large business processes.
* Perform HTTP requests.
* Handle file uploads.

Keep models lightweight.

---

# 8. Database Transactions

Operations affecting multiple tables should use transactions.

Example:

```php
DB::transaction(function () {
    ...
});
```

This ensures data consistency.

---

# 9. File Structure

Recommended structure:

```
app/

├── Helpers/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Models/
├── Services/
├── Policies/
└── Providers/
```

Avoid unnecessary directory nesting.

Good:

```
Services/
    UserService.php
    ProductService.php
```

Avoid:

```
Services/
    User/
        Create/
            CreateUserService.php
```

unless the module becomes significantly large.

---

# 10. Database Queries

Prefer Eloquent ORM.

Use:

* Relationships
* Query Scopes
* Eager Loading

Example:

```php
User::with('roles')->get();
```

Avoid:

```php
foreach (...) {
    User::find(...);
}
```

to prevent N+1 query issues.

---

# 11. Naming Conventions

## Models

Singular:

```
User
Product
Category
Order
```

## Controllers

```
UserController
ProductController
```

## Services

```
UserService
ProductService
```

## Requests

```
StoreUserRequest
UpdateUserRequest
```

## Migrations

Use Laravel's default naming convention.

---

# 12. API Responses

Use consistent JSON responses.

Example:

```json
{
    "success": true,
    "message": "Product created successfully.",
    "data": {}
}
```

Error responses should follow the same structure.

---

# 13. Business Logic Placement

| Responsibility     | Location     |
| ------------------ | ------------ |
| HTTP Handling      | Controller   |
| Validation         | Form Request |
| Business Logic     | Service      |
| Utility Functions  | Helper       |
| Database Relations | Model        |
| Authorization      | Policy       |

---

# 14. Code Readability

The code should prioritize readability over excessive abstraction.

A developer should be able to understand the application's execution flow by reading:

1. Route
2. Controller
3. Request
4. Service
5. Model

Avoid creating unnecessary layers that obscure the business process.

---

# 15. Development Philosophy

This project follows a pragmatic Laravel architecture:

* Thin Controllers.
* Dedicated Form Requests for validation.
* Services for business logic.
* Helpers for reusable utilities.
* Lightweight Models.
* Minimal but meaningful abstraction.
* Clear and maintainable project structure.

The primary objective is to ensure that any developer can quickly understand, maintain, and extend the backend without navigating through excessive service layers or overly complex architectural patterns.
