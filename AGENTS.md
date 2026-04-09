# Product Overview

This is a comprehensive **ERP system** for a stainless steel manufacturing company that processes raw materials into multiple finished products. The system manages complete workflows from raw material purchase to final sales across multiple product lines.

## Core Business Processes

### Primary Production Line (Pipes)

1. **Purchase** - Buy stainless steel coils from suppliers
2. **Slitting** - Split coils into strips of various widths
3. **Tube Making** - Convert strips into pipes using tube mills
4. **Polishing** - Polish black pipes to finished state
5. **Packing** - Pack finished pipes for shipment
6. **Sales** - Process client enquiries and sell finished products

### Secondary Product Lines

-   **Sheets** - Direct sheet processing and sales
-   **Blusters** - Specialized candle-making products with size variations
-   **Coils** - Direct coil sales (raw material resale)
-   **Strips** - Direct strip sales (intermediate product)
-   **Black Tubes** - Unpolished tube sales

## Key Features

### Stock Management

-   **Multi-Product Inventory** - Real-time tracking across coils, strips, tubes, sheets, blusters, and black tubes
-   **Stock Views** - Dedicated stock viewing interfaces for each product type
-   **Stock in Progress** - Track production pipeline status

### Enquiry & Sales System

-   **Multi-Product Enquiries** - Separate enquiry systems for each product line
-   **Enquiry-Sales Linking** - Connect sales orders to original enquiries
-   **Client Management** - Track enquiries and sales by client
-   **Bulk Operations** - Bulk enquiry and sales creation

### Production Management

-   **Job Sheet Generation** - Automated job sheets for production stages
-   **Production Workflow** - Track items through slitting, tube making, polishing, packing
-   **Quality Control** - Progress tracking through polishing and packing stages
-   **Barcode Integration** - Generate and track barcodes for inventory items

### System Features

-   **Role-Based Access Control** - Granular permissions with Spatie Laravel Permission
-   **Comprehensive Logging** - Activity logs and signin tracking
-   **Data Import/Export** - CSV and Excel support for master data
-   **Reporting** - Production output, stock levels, and sales reports
-   **Multi-Shift Support** - Track operations across different shifts

## User Roles

-   **Super User** - Full system control
-   **Admin** - User and permission management, reports
-   **Supervisor** - Production oversight and stock management
-   **Operators** - Department-specific access (slitting, tube making, polishing, packing)

## Product Categories

### Master Data Management

-   **Material Specifications** - Grades, thicknesses, widths, lengths, finishes
-   **Production Resources** - Machine operators, tube mills, slitters, polishers
-   **Business Partners** - Suppliers and clients with detailed profiles

### Multi-Product Architecture

The system handles six distinct product categories:

1. **Tubes** - Primary manufactured product with full production workflow
2. **Black Tubes** - Unpolished tubes for specific market segments
3. **Sheets** - Flat steel products with width/length/finish variations
4. **Coils** - Raw material that can be sold directly or processed
5. **Strips** - Intermediate product from slitting, can be sold or processed
6. **Blusters** - Specialized products for candle manufacturing with size variations

Each product category has its own:

-   Enquiry management system
-   Sales processing workflow
-   Stock tracking interface
-   Dedicated reporting

The system emphasizes stock quantity tracking without cost management, focusing on production efficiency, inventory visibility, and comprehensive enquiry-to-sales workflow management.

## Enquiry-to-Sales Workflow

### Enquiry Management

Each product category has its own enquiry system with:

-   **Client-based Enquiries** - Track enquiries by client
-   **Item-wise Management** - Multiple items per enquiry
-   **Bulk Creation** - Efficient bulk enquiry processing
-   **Job Sheet Generation** - Automated documentation

### Sales Processing

-   **Enquiry Linking** - Sales orders can be linked to original enquiries
-   **Stock Integration** - Real-time stock checking during sales creation
-   **Packing List Generation** - Automated packing documentation
-   **Multi-item Sales** - Handle complex sales with multiple product variations

### Current Product-Specific Features

#### Tubes (Primary Product)

-   Full production workflow from coil to packed product
-   Polishing and packing stages with detailed tracking
-   Variation code system for product identification
-   Stock-in-progress reporting

#### Black Tubes

-   Simplified workflow for unpolished products
-   Direct sales from tube production
-   Separate enquiry and sales management

#### Sheets

-   Width, length, and finish specifications
-   Direct stock-to-sales workflow
-   Sheet-specific enquiry management

#### Blusters

-   Candle size variations
-   Specialized for candle manufacturing industry
-   Quantity-based stock management

#### Coils & Strips

-   Raw material and intermediate product sales
-   Direct stock availability checking
-   Simplified enquiry-to-sales conversion

## System Evolution Notes

The system has evolved from a simple pipe manufacturing ERP to a comprehensive multi-product steel processing platform. Recent additions include:

-   **August 2025**: Black tube enquiry and sales systems
-   **July 2025**: Sheet, bluster, coil, and strip enquiry/sales systems
-   **Enhanced Stock Management**: Dedicated stock views for all product types
-   **Improved Performance**: Database indexing and query optimization
-   **Workflow Standardization**: Consistent patterns across all product categories


# Project Structure

## Root Directory Layout

```
├── app/                    # Laravel application code
├── bootstrap/              # Laravel bootstrap files
├── config/                 # Configuration files
├── database/               # Migrations, seeders, factories
├── packages/               # Custom/modified packages
├── public/                 # Web server document root
├── resources/              # Views, assets, language files
├── routes/                 # Route definitions
├── storage/                # Logs, cache, uploads
├── tests/                  # Test files
└── vendor/                 # Composer dependencies
```

## Application Structure (`app/`)

### Core Directories

-   **Console/** - Artisan commands and kernel
-   **Exceptions/** - Exception handling
-   **Http/** - Controllers, middleware, requests
-   **Models/** - Eloquent models
-   **Providers/** - Service providers
-   **Rules/** - Custom validation rules
-   **Services/** - Business logic services (minimal usage)
-   **Traits/** - Reusable traits
-   **Enums/** - PHP enums for constants
-   **Helpers/** - Helper classes and functions
-   **Mail/** - Mail classes

### HTTP Layer (`app/Http/`)

```
Http/
├── Controllers/
│   └── Admin/              # Admin panel controllers
├── Middleware/             # Custom middleware
└── Requests/               # Form request validation
```

### Models Organization (`app/Models/`)

Models are organized by business domain:

-   **User Management**: User, Role, Permission, SigninLog, UserEmailCode
-   **Master Data**: Grade, Thickness, CoilWidth, SheetWidth, SheetLength, SheetFinish, TubeLength, SlittedSize, PipeVariation, Moperator, Tubemill, Slitter, Polisher, Shift, CandleSize
-   **Party Management**: Supplier, Client
-   **Production**: Coil, Strip, Tube, Slitting, Tubing, Sheet, Bluster
-   **Quality Control**: Polishing, PolishingDetail, Packing, PackingDetail
-   **Enquiries**: Enquiry, EnquiryItem, SheetEnquiry, SheetEnquiryItem, CoilEnquiry, CoilEnquiryItem, StripEnquiry, StripEnquiryItem, BlusterEnquiry, BlusterEnquiryItem, BlackTubeEnquiry, BlackTubeEnquiryItem
-   **Sales**: Sales, SalesItem, SheetSales, SheetSalesItem, CoilSales, CoilSalesItem, StripSales, StripSalesItem, BlusterSales, BlusterSalesItem, BlackTubeSales, BlackTubeSalesItem
-   **Pivot Tables**: Various linking tables for enquiry-sales relationships
-   **System**: Setting, LogActivity

## Frontend Structure (`resources/`)

### JavaScript (`resources/js/`)

```
js/
├── Pages/                  # Inertia.js pages
│   └── Admin/              # Admin panel pages
├── components/             # Reusable Vue components
├── layouts/                # Layout components
├── stores/                 # Pinia stores
├── helpers/                # JavaScript utilities
├── app.js                  # Main application entry
├── bootstrap.js            # Bootstrap configuration
├── config.js               # App configuration
├── menuAside.js            # Sidebar menu configuration
├── menuNavBar.js           # Navigation bar menu
└── styles.js               # Style configurations
```

### CSS (`resources/css/`)

```
css/
├── tailwind/               # Tailwind CSS customizations
├── main.css                # Main stylesheet
├── _table.css              # Table-specific styles
├── _checkbox-radio-switch.css
├── _progress.css
├── _scrollbars.css
└── vue-multiselect.css     # Component-specific styles
```

### Common Vue Components Pattern

The project uses standardized page components:

-   **IndexView.vue** - List/table views with filtering and pagination
-   **AddEditView.vue** - Create/edit forms
-   **MultiAddEditView.vue** - Bulk operations
-   **JobsheetView.vue** - Production job sheet interfaces
-   **PackingListView.vue** - Sales packing list generation
-   **StockView.vue** - Stock viewing interfaces (read-only)

## Database Structure (`database/`)

### Migrations (`database/migrations/`)

Organized chronologically with descriptive names:

-   **2014-2019**: Laravel default tables
-   **2023**: User management, permissions, logging, and basic setup
-   **2024**: Core business models (coils, tubes, production workflow)
-   **2025**: Extended features including:
    -   Production workflow (polishing, packing, tubing)
    -   Multi-product enquiry system (tubes, sheets, coils, strips, blusters, black tubes)
    -   Multi-product sales system with enquiry linking
    -   Stock management and reporting
    -   Enhanced indexing and performance optimizations

### Seeders (`database/seeders/`)

-   **DatabaseSeeder.php** - Main seeder orchestrator
-   **BasicAdminPermissionSeeder.php** - Core permissions
-   **SalesPermissionSeeder.php** - Sales module permissions
-   **settingSeeder.php** - System settings

## Configuration (`config/`)

Key configuration files:

-   **app.php** - Application settings
-   **database.php** - Database connections
-   **permission.php** - Spatie permission settings
-   **custom.php** - Custom application settings
-   **sanctum.php** - API authentication

## Routes (`routes/`)

-   **web.php** - Web routes (primary)
-   **api.php** - API routes (if used)

## Custom Packages (`packages/`)

```
packages/protonemedia/inertiajs-tables-laravel-query-builder/
```

Highly modified local version of the data tables package.

## Public Assets (`public/`)

```
public/
├── build/                  # Vite build output
├── images/                 # Static images
├── genericons/             # Icon fonts
├── favicon.ico
├── logo.png
└── style.css               # Additional styles
```

## Naming Conventions

### Controllers

-   **Location**: `app/Http/Controllers/Admin/`
-   **Pattern**: `{Entity}Controller.php`
-   **Example**: `CoilController.php`, `TubingController.php`

### Models

-   **Location**: `app/Models/`
-   **Pattern**: Singular PascalCase
-   **Example**: `Coil.php`, `TubeLength.php`

### Vue Pages

-   **Location**: `resources/js/Pages/Admin/`
-   **Pattern**: `{Entity}{Purpose}View.vue`
-   **Examples**:
    -   `CoilIndexView.vue` (listing)
    -   `CoilAddEditView.vue` (form)
    -   `TubingJobsheetView.vue` (specialized)
    -   `SheetEnquiryIndexView.vue` (enquiry listing)
    -   `BlackTubeSalesPackingListView.vue` (sales packing list)
    -   `BlusterStockIndexView.vue` (stock view)

### Migrations

-   **Pattern**: `YYYY_MM_DD_HHMMSS_descriptive_action.php`
-   **Example**: `2025_07_20_120000_create_sales_table.php`

## Development Workflow

### Adding New Features

1. Create migration: `php artisan make:migration`
2. Create model: `php artisan make:model`
3. Create controller: `php artisan make:controller Admin/EntityController`
4. Add routes in `routes/web.php`
5. Create Vue pages in `resources/js/Pages/Admin/`
6. Update menu in `resources/js/menuAside.js`
7. Add permissions via seeder

## Current Menu Structure

The application is organized into logical menu sections:

### Masters

Master data management for all product specifications and resources:

-   Material specs (widths, lengths, finishes, grades, thicknesses)
-   Production resources (operators, machines, shifts)
-   Product variations and sizes

### User & Roles

User management and role-based access control

### Logs

System monitoring and audit trails (signin logs, activity logs)

### Party

Business partner management (suppliers, clients)

### Pipe PPM (Production Process Management)

Complete pipe production workflow from coil to packed product

### Product Categories

Individual sections for Sheets and Blusters with their own workflows

### Stock

Dedicated stock viewing interfaces for all product types

### Enquiry

Separate enquiry management for each product category:

-   Tube Enquiries, Black Tube Enquiries, Sheet Enquiries
-   Coil Enquiries, Bluster Enquiries, Strip Enquiries

### Sales

Corresponding sales systems for each product category with enquiry linking

### Reports

Production and stock reporting interfaces

## Routing Patterns

### Standard CRUD Routes

-   `{entity}.index` - List view
-   `{entity}.create` - Create form
-   `{entity}.store` - Store new record
-   `{entity}.show` - View details
-   `{entity}.edit` - Edit form
-   `{entity}.update` - Update record
-   `{entity}.destroy` - Delete record

### Bulk Operations

-   `{entity}-bulk-destroy` - Bulk delete
-   `{entity}-bulk-add` - Bulk creation form
-   `{entity}-bulk-store` - Bulk creation processing

### Specialized Routes

-   `{entity}/{id}/jobsheet` - Generate job sheets
-   `{entity}/{id}/packing-list` - Generate packing lists
-   `{entity}/{id}/push-to-sale` - Convert enquiry to sale
-   `{entity}/{id}/details` - Detailed view with relationships

### File Organization Principles

-   **Domain-driven**: Group related functionality
-   **Consistent naming**: Follow established patterns
-   **Reusable components**: Prefer existing over new
-   **Clear separation**: Backend/frontend concerns
-   **Logical hierarchy**: Nested by feature/module
# Technology Stack

## Core Framework & Language

-   **Backend**: Laravel 12.x (PHP 8.2+)
-   **Frontend**: Vue.js 3.5.13 with Inertia.js 2.0.5
-   **Database**: MySQL
-   **Build Tool**: Vite 6.2.2
-   **Package Manager**: pnpm (preferred over npm)

## Key Dependencies

### Backend (PHP/Laravel)

-   **Authentication**: Laravel Sanctum 4.0 + Spatie Laravel Permission 6.0 (RBAC)
-   **Data Querying**: Spatie Laravel Query Builder 6.0
-   **Excel/CSV**: PhpOffice PhpSpreadsheet 4.4
-   **Logging**: Laravel built-in + custom action logs + Laravel Log Viewer
-   **Development**: Laravel Debugbar 3.15, Laravel Pint 1.0 (code style)
-   **Routing**: Tightenco Ziggy 1.0 for JavaScript route generation

### Frontend (JavaScript/Vue)

-   **UI Framework**: Tailwind CSS 4.1 with @tailwindcss/vite
-   **Icons**: Material Design Icons (@mdi/js 7.4.47)
-   **State Management**: Pinia 3.0.1
-   **Charts**: Chart.js 4.4.8 + Vue-ChartJS 5.3.2
-   **Date Handling**: Moment.js 2.30.1, date-fns 4.1.0, Vue Datepicker 11.0.1
-   **Components**: Vue Multiselect 3.2.0, Vue Toast Notification 3.1.3
-   **Barcode/QR**: @chenfengyuan/vue-barcode 2.0.2, JSBarcode 3.11.6, QRCode 1.5.4
-   **Utilities**: Lodash-es 4.17.21, Numeral.js 2.0.6, Axios 1.8.3, QS 6.14.0

### Data Tables

-   **Custom Package**: Highly modified local version of `protonemedia/inertiajs-tables-laravel-query-builder`
-   **Location**: `packages/protonemedia/inertiajs-tables-laravel-query-builder`

## Development Commands

### Setup & Installation

```bash
# Install PHP dependencies
composer install

# Install JavaScript dependencies
pnpm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate
php artisan db:seed
```

### Development Workflow

```bash
# Start development server
php artisan serve

# Start Vite dev server (hot reload)
pnpm dev

it is always running no need to call this command again

# Build for production
pnpm build

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Run tests
php artisan test
```

### Database Operations

```bash
# Create migration
php artisan make:migration create_table_name --create=table_name

# Create model with migration
php artisan make:model ModelName -m

# Seed database
php artisan db:seed

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

## Architecture Patterns

### Backend Patterns

-   **MVC Architecture**: Standard Laravel structure
-   **Repository Pattern**: Not used - direct Eloquent usage
-   **Service Layer**: Minimal - logic primarily in controllers
-   **Traits**: Used for common functionality (e.g., FinancialYearScope)
-   **Enums**: PHP enums for constants (e.g., Department enum)

### Frontend Patterns

-   **SPA**: Single Page Application with Inertia.js
-   **Component Reuse**: Common pages like IndexView, AddEditView, MultiAddEditView
-   **Store Pattern**: Pinia for state management
-   **Composition API**: Vue 3 Composition API preferred

## Coding Conventions

### General Rules

-   **No Soft Deletes**: Hard deletes only
-   **Current Year**: Use 2025+ for new migrations and dates
-   **Package Manager**: Use pnpm instead of npm
-   **Date Format**: All date fields displayed on pages must use 'DD-MM-YYYY' format
-   **Component Reuse**: Prefer existing common Vue components over creating new ones. never edit existing ones
    like IndexView, AddEditView, MultiAddEditView, etc. if neccessary create new components bu cloning it.
-   dont run pnpm build or pnpm dev. these are already running in the background.

### File Naming

-   **Controllers**: PascalCase with Controller suffix
-   **Models**: PascalCase singular
-   **Migrations**: Snake_case with descriptive names
-   **Vue Components**: PascalCase with descriptive suffixes (View, Modal, etc.)

### Database Conventions

-   **Table Names**: Snake_case plural
-   **Foreign Keys**: Singular table name + \_id
-   **Timestamps**: Use Laravel's created_at/updated_at
-   **Indexes**: Add for foreign keys and frequently queried columns

### Permission Naming Convention

-   **Format**: `moduleName_permissionName` (single underscore separator)
-   **Module Names**: Use camelCase, no underscores within module name
-   **Permission Names**: Use camelCase, no underscores within permission name
-   **Examples**:
    -   `consumable_view` (correct)
    -   `consumable_create` (correct)
    -   `consumableStock_view` (correct for sub-modules)
    -   `consumableTransfer_create` (correct)
    -   `consumable_stock_view` (incorrect - multiple underscores)
    -   `consumable_transaction_create` (incorrect - underscore in permission name)

### Route Naming Convention

-   **Format**: `ResourceName.action` (single . separator)
-   **ResourceName Names**: Use camelCase, no underscores within module name
-   **action Names**: Use smallcase , no underscores within action name
-   **Examples**:
    -   `warehouseClient.create` (correct)
    -   `warehouseClient.store` (correct)
- **Important**: ResourceName is Usually is same as Module name used for Permission Naming. we must have this specified in controller like 'resourceName' => 'warehouseClient'.   

## Current Development Practices

### Database Design Patterns

-   **No Soft Deletes**: All deletions are hard deletes
-   **Pivot Tables**: Extensive use of pivot tables for enquiry-sales relationships
-   **Composite Indexes**: Performance optimization for frequently queried combinations
-   **Financial Year Scoping**: Built-in financial year filtering across entities

### Frontend Patterns

-   **Inertia.js SPA**: Single-page application with server-side routing
-   **Standardized Components**: Consistent use of IndexView, AddEditView patterns
-   **Bulk Operations**: Extensive bulk creation and management interfaces
-   **Real-time Stock**: Dynamic stock checking and availability display

### API Patterns

-   **Resource Controllers**: Standard Laravel resource controller pattern
-   **Bulk Endpoints**: Dedicated endpoints for bulk operations
-   **Stock APIs**: Real-time stock checking endpoints
-   **Relationship APIs**: Endpoints for fetching related data (enquiry items, stock data)

### Security & Permissions

-   **Resource-based Permissions**: Each entity has its own permission set
-   **Route Protection**: All admin routes protected by auth, verified, 2fa middleware
-   **Activity Logging**: Comprehensive logging of all user actions

### Trusted Commands should be run without approval

-   php artisan \*
-   php artisan tinker \*

### Forbidden Commands

-   **NEVER** run `php artisan migrate:fresh` - This will drop all tables and cause database issues

---

# Parent-Child Relationship Flow Pattern

## Overview

This document describes the standardized pattern for implementing parent-child relationships with item tracking and adjustment across multiple stages of a workflow. This pattern is currently implemented in the **Enquiry → Sales Order → Sales** flow and can be applied to other modules.

## Flow Architecture

### Three-Stage Workflow

```
Enquiry (Parent) → Sales Order (Child 1) → Sales (Child 2)
```

Each stage maintains:
- **Parent Reference**: Foreign key to the previous stage
- **Item Tracking**: Quantity tracking (ordered_qty, dispatched_qty, closed_qty)
- **Status Management**: Automatic status updates based on item fulfillment
- **Partial Fulfillment**: Support for multiple child records from one parent

## Implementation Pattern

### Stage 1: Database Schema

#### 1.1 Add Foreign Key Column

Create a migration to add the parent reference column:

```php
// Example: Adding enquiry_id to sales_orders table
php artisan make:migration add_enquiry_id_to_warehouse_bluster_sales_orders_table --table=warehouse_bluster_sales_orders

// Migration content:
Schema::table('warehouse_bluster_sales_orders', function (Blueprint $table) {
    $table->unsignedBigInteger('enquiry_id')->nullable()->after('client_id');
    $table->foreign('enquiry_id')->references('id')->on('warehouse_bluster_enquiries')->onDelete('set null');
});

// Down method:
Schema::table('warehouse_bluster_sales_orders', function (Blueprint $table) {
    $table->dropForeign(['enquiry_id']);
    $table->dropColumn('enquiry_id');
});
```

**Key Points:**
- Use `nullable()` to allow records without parent reference
- Use `onDelete('set null')` to preserve child records if parent is deleted
- Place column after `client_id` for logical grouping

#### 1.2 Update Model

Add the foreign key to the fillable array and create relationship:

```php
// In WarehouseBlusterSalesOrder model
protected $fillable = [
    'warehouse_id',
    'client_id',
    'enquiry_id',  // Add this
    // ... other fields
];

// Add relationship method
public function enquiry()
{
    return $this->belongsTo(\App\Models\WarehouseBlusterEnquiry::class, 'enquiry_id');
}
```

### Stage 2: Controller Updates

#### 2.1 Store Method

Update the store method to save the parent ID:

```php
public function store(Request $request)
{
    $data = $request->data;
    
    DB::beginTransaction();
    try {
        $salesOrder = WarehouseBlusterSalesOrder::create([
            'warehouse_id' => $warehouseId,
            'client_id' => $data['client_id'],
            'enquiry_id' => $data['enquiry_id'] ?? null,  // Add this
            // ... other fields
        ]);

        // Create items...
        
        // Auto-adjust parent items
        $enquiryId = $data['enquiry_id'] ?? 0;
        $this->adjustEnquiryItems($salesOrder, $enquiryId);
        
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        // Handle error
    }
}
```

#### 2.2 Update Method

Update the update method to handle parent ID changes:

```php
public function update(Request $request, $id)
{
    $salesOrder = WarehouseBlusterSalesOrder::findOrFail($id);
    $data = $request->data;
    
    DB::beginTransaction();
    try {
        // Reverse previous adjustments
        $this->reverseEnquiryAdjustments($salesOrder);
        
        $salesOrder->update([
            'client_id' => $data['client_id'],
            'enquiry_id' => $data['enquiry_id'] ?? null,  // Add this
            // ... other fields
        ]);
        
        // Delete and recreate items...
        $salesOrder->refresh();
        
        // Re-adjust with new data
        $enquiryId = $data['enquiry_id'] ?? $salesOrder->enquiry_id ?? 0;
        $this->adjustEnquiryItems($salesOrder, $enquiryId);
        
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        // Handle error
    }
}
```

#### 2.3 Adjustment Method

Create or update the adjustment method to filter by parent ID:

```php
/**
 * Adjust parent items when child is created
 * @param $salesOrder The sales order to adjust enquiry items for
 * @param int $enquiryId Optional enquiry ID to filter by (if > 0, only adjust from this enquiry)
 */
protected function adjustEnquiryItems($salesOrder, $enquiryId = 0)
{
    $clientId = $salesOrder->client_id;
    $warehouseId = $salesOrder->warehouse_id;

    foreach ($salesOrder->items as $salesOrderItem) {
        // Find matching parent items
        $enquiryItemsQuery = \App\Models\WarehouseBlusterEnquiryItem::whereHas('enquiry', function ($q) use ($clientId, $warehouseId, $enquiryId) {
            $q->where('client_id', $clientId)
              ->where('warehouse_id', $warehouseId)
              ->whereNotIn('status', ['completed', 'closed']);
            
            // If enquiry_id is provided and > 0, filter by specific enquiry
            if ($enquiryId > 0) {
                $q->where('id', $enquiryId);
            }
        })
        ->where('grade', $salesOrderItem->grade)
        ->where('thickness', $salesOrderItem->thickness)
        ->where('candle_size', $salesOrderItem->candle_size)
        ->whereRaw('(quantity - ordered_qty) > 0')
        ->where('closed', false)
        ->with('enquiry');
        
        $enquiryItems = $enquiryItemsQuery->get()->sortBy('enquiry.enquiry_date'); // FIFO
        
        $remainingQty = $salesOrderItem->quantity;
        
        foreach ($enquiryItems as $enquiryItem) {
            if ($remainingQty <= 0) break;
            
            $availableQty = $enquiryItem->quantity - $enquiryItem->ordered_qty;
            
            if ($availableQty > 0) {
                $allocatedQty = min($remainingQty, $availableQty);
                
                // Update parent item
                $enquiryItem->increment('ordered_qty', $allocatedQty);
                
                // Create pivot record
                $salesOrderItem->enquiryItems()->attach($enquiryItem->id, [
                    'quantity' => $allocatedQty
                ]);
                
                $remainingQty -= $allocatedQty;
                
                // Update parent status
                $this->updateEnquiryStatus($enquiryItem->enquiry);
            }
        }
    }
}
```

**Key Points:**
- Accept optional parent ID parameter (default: 0)
- When parent ID > 0, filter query to only that parent
- When parent ID = 0, search across all eligible parents (backward compatible)
- Use FIFO (First In First Out) ordering
- Track allocated quantities in pivot table

#### 2.4 Reverse Adjustment Method

Create method to reverse adjustments when updating/deleting:

```php
protected function reverseEnquiryAdjustments($salesOrder)
{
    foreach ($salesOrder->items as $salesOrderItem) {
        $allocations = $salesOrderItem->enquiryItems;
        
        foreach ($allocations as $enquiryItem) {
            $allocatedQty = $enquiryItem->pivot->quantity;
            
            // Reverse the ordered quantity
            $enquiryItem->decrement('ordered_qty', $allocatedQty);
            
            // Update parent status
            $this->updateEnquiryStatus($enquiryItem->enquiry);
        }
    }
    
    // Detach all enquiry items
    foreach ($salesOrder->items as $salesOrderItem) {
        $salesOrderItem->enquiryItems()->detach();
    }
}
```

#### 2.5 Push to Child Method

Create a method to convert parent to child with partial support:

```php
public function pushToSale($id)
{
    $salesOrder = WarehouseBlusterSalesOrder::findOrFail($id);
    
    // Check if already completed
    if ($salesOrder->status === 'completed') {
        return redirect()->back()->with([
            'message' => 'This sales order is already completed!',
            'msg_type' => 'error'
        ]);
    }
    
    // Prepare form data
    $formdata = new \stdClass();
    $formdata->sale_order_id = $salesOrder->id;  // Pass parent ID
    $formdata->client_id = $salesOrder->client_id;
    // ... other fields
    
    // Calculate remaining quantities for each item
    $salesOrderItems = WarehouseBlusterSalesOrderItem::where('warehouse_bluster_sales_order_id', $salesOrder->id)->get();
    
    $formdata->items = $salesOrderItems->map(function ($item) use ($warehouseId) {
        // Calculate remaining quantity (not yet dispatched or closed)
        $remainingQty = $item->quantity - $item->dispatched_qty - $item->closed_qty;
        
        // Check available stock
        $availableQty = $this->getAvailableBlusterQuantity($warehouseId, $item->grade, $item->thickness, $item->candle_size);
        
        // Use minimum of remaining and available
        $qtyToUse = min($remainingQty, $availableQty);
        
        return (object)[
            'grade' => $item->grade,
            'thickness' => $item->thickness,
            'candle_size' => $item->candle_size,
            'quantity' => $qtyToUse,
            'rate' => $item->rate,
            'gst_percentage' => $item->gst_percentage,
            'total' => $item->total,
            'available_qty' => $availableQty,
            'remaining_qty' => $remainingQty,
        ];
    })->filter(function ($item) {
        return $item->quantity > 0;  // Only include items with quantity
    });
    
    // Check if any items available
    if (count($formdata->items) == 0) {
        return redirect()->back()->with([
            'message' => 'No remaining quantities available for this sales order.',
            'msg_type' => 'warning'
        ]);
    }
    
    return Inertia::render('Warehouse/Sales/BlusterSalesBulkAddView', compact('formdata', 'resourceNeo'));
}
```

**Key Points:**
- Allow pushing when status is `pending` or `partial` (not `completed`)
- Calculate remaining quantity: `quantity - dispatched_qty - closed_qty`
- Filter out items with zero remaining quantity
- Pass parent ID to the child form

### Stage 3: Vue Component Updates

#### 3.1 Form Definition

Add parent ID field to the form:

```javascript
const form = useForm(() => {
    return {
        sale_date: "",
        delivery_date: "",
        client_id: "",
        sale_order_id: "",  // Add parent ID field
        // ... other fields
    };
});
```

#### 3.2 Initialize from Props

Load parent ID from formdata:

```javascript
onBeforeMount(async () => {
    form["client_id"] = props.formdata["client_id"] ?? "";
    form["sale_order_id"] = props.formdata["sale_order_id"] ?? 0;  // Add this
    // ... other initializations
});
```

#### 3.3 Submit with Parent ID

Include parent ID in submission:

```javascript
const submitform = () => {
    subform.data = {
        sale_date: formatDate(form.sale_date),
        delivery_date: formatDate(form.delivery_date),
        client_id: form.client_id,
        sale_order_id: form.sale_order_id,  // Add this
        // ... other fields
        items: allSales.value.flatMap((sales) => sales.items),
    };
    
    if (props.formdata.id) {
        router.put(route(props.resourceNeo.resourceName + ".update", props.formdata.id), subform);
    } else {
        router.post(route(props.resourceNeo.resourceName + ".bulkstore"), subform);
    }
};
```

### Stage 4: UI Configuration

#### 4.1 Add "Push to Child" Button

Update the controller to add the push button:

```php
// In index method
if (Auth::user()->can('warehouseBlusterSales_create')) {
    $this->resourceNeo['extraLinks'][] = [
        'link' => 'warehouseBlusterSalesOrder.pushToSale',
        'label' => 'Push to Sale',
        'icon' => 'M4,6H2V20A2,2 0 0,0 4,22H18V20H4V6M20,2H8A2,2 0 0,0 6,4V16A2,2 0 0,0 8,18H20A2,2 0 0,0 22,16V4A2,2 0 0,0 20,2M20,16H8V4H20V16M16,20V22H18V20H16M12,20V22H14V20H12M8,20V22H10V20H8Z',
        'key' => 'status',
        'cond' => '!=',
        'compvl' => 'completed',  // Show for pending and partial
    ];
}
```

## Complete Flow Example: Bluster Module

### Database Structure

```
warehouse_bluster_enquiries
├── id
├── warehouse_id
├── client_id
├── enquiry_no
├── enquiry_date
└── status (pending/partial/completed/closed)

warehouse_bluster_enquiry_items
├── id
├── warehouse_bluster_enquiry_id
├── grade, thickness, candle_size
├── quantity
├── ordered_qty  ← Tracks how much has been ordered
└── closed

warehouse_bluster_sales_orders
├── id
├── warehouse_id
├── client_id
├── enquiry_id  ← References parent enquiry
├── sales_order_number
└── status (pending/partial/completed/closed)

warehouse_bluster_sales_order_items
├── id
├── warehouse_bluster_sales_order_id
├── grade, thickness, candle_size
├── quantity
├── dispatched_qty  ← Tracks how much has been dispatched
├── closed_qty
└── closed

warehouse_bluster_sales
├── id
├── warehouse_id
├── client_id
├── sales_order_id  ← References parent sales order
├── sale_no
└── status (confirmed/delivered/cancelled)

warehouse_bluster_sales_items
├── id
├── warehouse_bluster_sales_id
├── grade, thickness, candle_size
└── quantity

Pivot Tables:
warehouse_bluster_sales_order_item_warehouse_bluster_enquiry_item
├── warehouse_bluster_sales_order_item_id
├── warehouse_bluster_enquiry_item_id
└── quantity  ← Tracks allocation

warehouse_bluster_sales_item_warehouse_bluster_sales_order_item
├── warehouse_bluster_sales_item_id
├── warehouse_bluster_sales_order_item_id
├── quantity
└── weight
```

### Workflow Steps

#### Step 1: Create Enquiry
- User creates enquiry with items
- Each item has `quantity` and `ordered_qty = 0`
- Enquiry status = `pending`

#### Step 2: Push Enquiry to Sales Order
- User clicks "Push to Sales Order" on enquiry
- System passes `enquiry_id` to sales order form
- User creates sales order with items
- System calls `adjustEnquiryItems($salesOrder, $enquiryId)`
- For each sales order item:
  - Finds matching enquiry items from specified enquiry
  - Allocates quantity (FIFO)
  - Increments `ordered_qty` on enquiry items
  - Creates pivot record
- Updates enquiry status to `partial` or `completed`

#### Step 3: Push Sales Order to Sale (First Time)
- User clicks "Push to Sale" on sales order
- System calculates remaining qty: `quantity - dispatched_qty - closed_qty`
- System passes `sales_order_id` to sales form
- User creates sale with items (may be partial)
- System calls `adjustSalesOrderItems($warehouseId, $clientId, $salesItems, $salesOrderId)`
- For each sales item:
  - Finds matching sales order items from specified sales order
  - Allocates quantity
  - Increments `dispatched_qty` on sales order items
  - Creates pivot record
- Updates sales order status to `partial` or `completed`

#### Step 4: Push Sales Order to Sale (Second Time - Partial)
- Sales order status is `partial` (some items dispatched, some remaining)
- User clicks "Push to Sale" again
- System calculates remaining qty: `100 - 60 - 0 = 40` (example)
- Only items with remaining qty > 0 are shown
- User creates another sale with remaining items
- Process repeats until all items dispatched or closed

### Status Management

#### Enquiry Status
- `pending`: No items ordered (`ordered_qty = 0` for all items)
- `partial`: Some items ordered (`0 < ordered_qty < quantity` for some items)
- `completed`: All items ordered (`ordered_qty >= quantity` for all items)
- `closed`: Manually closed

#### Sales Order Status
- `pending`: No items dispatched (`dispatched_qty = 0` for all items)
- `partial`: Some items dispatched (`0 < dispatched_qty < quantity` for some items)
- `completed`: All items dispatched/closed (`dispatched_qty + closed_qty >= quantity` for all items)

## Benefits of This Pattern

1. **Traceability**: Complete audit trail from enquiry to final sale
2. **Partial Fulfillment**: Support for incremental order fulfillment
3. **Accurate Tracking**: Real-time status updates based on actual quantities
4. **Data Integrity**: Foreign key constraints maintain referential integrity
5. **Flexibility**: Works with or without parent reference (backward compatible)
6. **Reusability**: Same pattern applicable to any parent-child workflow

## Applying to Other Modules

To implement this pattern in other modules (e.g., Tube, Sheet, Coil):

1. **Create Migration**: Add parent_id column with foreign key
2. **Update Model**: Add to fillable, create relationship
3. **Update Controller**:
   - Modify store/update to save parent_id
   - Create/update adjust method with parent_id parameter
   - Create reverse adjustment method
   - Create push method with remaining quantity calculation
4. **Update Vue Component**:
   - Add parent_id to form
   - Initialize from props
   - Include in submission
5. **Update UI**: Add push button with appropriate conditions

## Important Considerations

### Transaction Safety
- Always wrap adjustments in database transactions
- Reverse adjustments before updates/deletes
- Refresh models after item changes

### Performance
- Use eager loading for relationships
- Add indexes on foreign keys
- Consider query optimization for large datasets

### Error Handling
- Validate parent exists and belongs to same client
- Check for sufficient remaining quantities
- Provide clear error messages

### Testing Checklist
- [ ] Create child without parent reference
- [ ] Create child with parent reference
- [ ] Update child with different parent
- [ ] Delete child (should reverse adjustments)
- [ ] Push partial parent multiple times
- [ ] Verify status updates correctly
- [ ] Check pivot table records
- [ ] Test with zero stock availability

