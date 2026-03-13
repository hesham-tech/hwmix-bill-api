# Product Entity Change Log

This file tracks all modifications, additions, and architectural decisions made during the Product Schema Synchronization task.

## 1. Database & Schema Changes

### Models Modifications

- **Product Model (`App\Models\Product`)**:
    - Added to `$fillable`: `product_type`, `require_stock`, `is_downloadable`, `download_url`, `download_limit`, `license_keys`, `available_keys_count`, `validity_days`, `expires_at`, `delivery_instructions`.
    - Added to `$casts`: `product_type` (string), `require_stock` (boolean), `is_downloadable` (boolean), `license_keys` (array), `available_keys_count` (integer), `validity_days` (integer), `expires_at` (datetime).
- **ProductVariant Model (`App\Models\ProductVariant`)**:
    - Added to `$fillable`: `profit_margin`, `weight`, `dimensions`, `tax`, `discount`, `status`.
    - Added to `$casts`: `profit_margin` (decimal:2).

## 2. Backend Logic Changes

### DTOs (Data Transfer Objects)

- **`ProductData`**: Added new product-level fields.
- **`VariantData`**: Added new variant-level fields.

### Requests (Validation)

- **`StoreProductRequest` / `UpdateProductRequest`**: Updated rules to validate type-specific fields.

### Resources (API Transformation)

- **`ProductResource`**: Included all new system fields in API response.
- **`ProductVariantResource`**: Included new pricing and status fields.

### Services

- **`ProductService`**: Updated logic to handle nested digital product data and automatic license key counting.

## 3. Frontend Changes

### Components

- **`ProductForm.vue`**:
    - Implemented "Smart Form" logic to toggle fields based on `product_type`.
    - Set default type to `physical`.
- **`VariantManager.vue`**:
    - Integrated `profit_margin` calculations and UI indicators.
    - Simplified layout for better UX.

### Store

- **`product.store.js`**: Initialized new state properties for products.
