# ProcessWire Matrix Type Module

A ProcessWire module for managing and displaying typed repeater matrix items with structured data extraction and flexible rendering.

**Version:** 1.1.0
**Repository:** github.com/mxmsmnv/InputfieldMatrixType  

**Author:** Maxim Semenov  
**Website:** [smnv.org](https://smnv.org)  
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).  
**License:** MIT

## The Problem

When working with ProcessWire's Repeater Matrix fields, identifying the type of each matrix item on the frontend is not straightforward:

- No built-in field exists to store human-readable type identifiers
- Extracting structured data from different matrix types requires repetitive code
- Creating JSON APIs or dynamic frontends becomes unnecessarily complicated

This module solves these problems by providing a dedicated fieldtype for storing type identifiers and a data processor for consistent extraction.

## Overview

- **FieldtypeMatrixType** — Custom fieldtype for storing matrix item type identifiers
- **InputfieldMatrixType** — Admin interface for configuring matrix types
- **MatrixDataProcessor** — PHP class for extracting and structuring matrix data

## Features

- Define unique type identifiers for each matrix item
- Set human-readable display names
- Automatic data extraction from repeater matrix fields
- Structured output format (JSON-ready)
- Built-in price and SKU field support
- Support for multiple field types (text, images, options, pages, etc.)
- Skip empty values automatically
- Preserve numeric zero values for number fields
- Filter system fields automatically
- Reliable type detection via native `repeater_matrix_type` + `getMatrixTypes()`

## Installation

1. Copy the module folder to `/site/modules/InputfieldMatrixType/`
2. Go to Admin → Modules → Refresh
3. Install **FieldtypeMatrixType** — InputfieldMatrixType installs automatically as a dependency

## Module Structure

```
/site/modules/InputfieldMatrixType/
├── FieldtypeMatrixType.module.php
├── InputfieldMatrixType.module.php
├── MatrixDataProcessor.php
└── README.md
```

## Setup

### Step 1 — Create identifier fields

For each matrix type, create a separate field of type **Matrix Type** (`FieldtypeMatrixType`).

### Step 2 — Configure each identifier field

Go to Admin → Fields → [field name] → Details tab and fill in:

- **Matrix Type Identifier** — unique slug used in code (e.g. `box`)
- **Display Name** — human-readable label (e.g. `Box`)

> &#x26A0; The Matrix Type Identifier is what `$item['type']` returns in your template. It must match exactly what you use in your switch/if statements.

### Step 3 — Add to matrix templates

In each matrix type template, add its corresponding identifier field. The field renders as a hidden input in the admin — no data entry needed from editors.

## Usage

### &#x26A0; Namespace requirement

Template files must declare the namespace, otherwise `new MatrixDataProcessor()` will fail:

```php
<?php namespace ProcessWire;
```

### Basic example

```php
<?php namespace ProcessWire;

$processorPath = wire('config')->paths->siteModules . 'InputfieldMatrixType/MatrixDataProcessor.php';
if (file_exists($processorPath)) require_once $processorPath;

$processor = new MatrixDataProcessor($page);
$items = $processor->getItems('your_matrix_field_name');
```

> Use `wire('config')` instead of `$config` — it works reliably in all template contexts.

### Data structure returned

```php
[
    [
        'id'          => 1234,
        'type'        => 'sedan',       // Matrix Type Identifier
        'displayName' => 'Sedan',       // Display Name
        'sku'         => 'VIN-001',     // SKU field value if present
        'price'       => 24900.00,      // Price field value if present
        'fields'      => [              // All non-system fields with values
            [
                'name'  => 'make',
                'label' => 'Make',
                'type'  => 'FieldtypeText',
                'value' => 'Toyota'
            ],
            [
                'name'  => 'year',
                'label' => 'Year',
                'type'  => 'FieldtypeInteger',
                'value' => 2022
            ],
            [
                'name'  => 'transmission',
                'label' => 'Transmission',
                'type'  => 'FieldtypeOptions',
                'value' => ['Automatic']
            ],
            [
                'name'  => 'all_wheel_drive',
                'label' => 'All Wheel Drive',
                'type'  => 'FieldtypeCheckbox',
                'value' => true
            ],
        ]
    ]
]
```

### Field value types

| ProcessWire fieldtype | PHP value returned |
|---|---|
| FieldtypeText / FieldtypeTextarea | string |
| FieldtypeInteger | int |
| FieldtypeFloat / FieldtypeDecimal | float |
| FieldtypeOptions | array of title strings, e.g. `['Red', 'Blue']` |
| FieldtypeCheckbox | bool |
| FieldtypePage | array with `id`, `title`, `url` — or array of such arrays |
| FieldtypeImage | array with `url`, `description`, `width`, `height` |
| FieldtypeFile | array with `url`, `description`, `filesize`, `basename` |

Empty values are skipped automatically — a field only appears in `$item['fields']` if it has a non-empty value. Numeric `0` is preserved for number fields; unchecked checkboxes are skipped.

## Examples

### Case 1 — Real estate: Apartment, House, Land

Each property type has a completely different set of fields. Matrix field name: `details`

**Identifier fields:**

| Field name | Matrix Type Identifier | Display Name |
|---|---|---|
| `details_apartment` | `apartment` | Apartment |
| `details_house` | `house` | House |
| `details_land` | `land` | Land |

**Fields per type:**

| Type | Fields |
|---|---|
| apartment | `rooms` (Integer), `floor` (Integer), `floors_total` (Integer), `area_sqm` (Float), `bathroom_type` (Options), `balcony` (Checkbox), `elevator` (Checkbox) |
| house | `area_sqm` (Float), `land_area_sqm` (Float), `floors` (Integer), `garage` (Checkbox), `heating_type` (Options) |
| land | `land_area_sqm` (Float), `land_category` (Options), `electricity` (Checkbox), `gas` (Checkbox), `water` (Checkbox) |

**Template:**

```php
<?php namespace ProcessWire;

$processorPath = wire('config')->paths->siteModules . 'InputfieldMatrixType/MatrixDataProcessor.php';
if (file_exists($processorPath)) require_once $processorPath;

$processor = new MatrixDataProcessor($page);
$items = $processor->getItems('details');

foreach ($items as $item):
?>
<div class="property-details" data-type="<?= $item['type'] ?>">
    <h3><?= $item['displayName'] ?></h3>

    <?php foreach ($item['fields'] as $f): ?>
    <?php
        $val = $f['value'];
        if (is_array($val)) $val = implode(', ', $val);
        if (is_bool($val)) $val = $val ? 'Yes' : 'No';
        if ($val === null || $val === '') continue;
    ?>
    <div class="detail-row">
        <span class="label"><?= htmlspecialchars($f['label']) ?></span>
        <span class="value"><?= htmlspecialchars((string)$val) ?></span>
    </div>
    <?php endforeach ?>
</div>
<?php endforeach ?>
```

---

### Case 2 — Alcohol shop: Wine, Cognac, Beer

Each drink type has its own set of characteristics. Matrix field name: `drinks`

**Identifier fields:**

| Field name | Matrix Type Identifier | Display Name |
|---|---|---|
| `drinks_wine` | `wine` | Wine |
| `drinks_cognac` | `cognac` | Cognac |
| `drinks_beer` | `beer` | Beer |

**Fields per type:**

| Type | Fields |
|---|---|
| wine | `grape_variety` (Options), `vintage_year` (Integer), `region` (Text), `sweetness` (Options: Dry / Semi-dry / Semi-sweet / Sweet), `color` (Options: Red / White / Rosé), `volume_ml` (Integer) |
| cognac | `age_years` (Integer), `region` (Text), `distillery` (Text), `volume_ml` (Integer) |
| beer | `style` (Options: Lager / Ale / Stout / IPA / Wheat), `ibu` (Integer), `abv` (Float), `filtered` (Checkbox), `volume_ml` (Integer) |

**Template:**

```php
<?php namespace ProcessWire;

$processorPath = wire('config')->paths->siteModules . 'InputfieldMatrixType/MatrixDataProcessor.php';
if (file_exists($processorPath)) require_once $processorPath;

$processor = new MatrixDataProcessor($page);
$items = $processor->getItems('drinks');

foreach ($items as $item):
?>
<div class="card" data-type="<?= $item['type'] ?>">
    <h3><?= $item['displayName'] ?></h3>

    <?php if ($item['price'] !== null): ?>
    <div class="price">$<?= number_format($item['price'], 2) ?></div>
    <?php endif ?>

    <?php foreach ($item['fields'] as $f): ?>
    <?php
        $val = $f['value'];
        if (is_array($val)) $val = implode(', ', $val);
        if (is_bool($val)) $val = $val ? 'Yes' : 'No';
        if ($val === null || $val === '') continue;
    ?>
    <div class="detail-row">
        <span class="label"><?= htmlspecialchars($f['label']) ?></span>
        <span class="value"><?= htmlspecialchars((string)$val) ?></span>
    </div>
    <?php endforeach ?>

    <button data-item-id="<?= $item['id'] ?>">Add to Cart</button>
</div>
<?php endforeach ?>
```

---

## Skip Fields

The processor automatically skips:

- System fields: `id`, `name`, `parent`, `template`, `created`, `modified`, `createdUser`, `modifiedUser`
- `repeater_matrix_type`
- `price`, `sku` (returned separately at the item level)
- All `FieldtypeMatrixType` fields (used for type detection only)

You can customize skipped fields without editing the class:

```php
$processor = new MatrixDataProcessor($page, [
    'addSkipFields' => ['internal_notes', 'supplier_cost']
]);

// Or replace the full skip list:
$processor = new MatrixDataProcessor($page, [
    'skipFields' => ['repeater_matrix_type', 'id', 'name']
]);
```

## Custom Formatting

Override `formatValue()` to add support for custom fieldtypes:

```php
protected function formatValue($value, $field) {
    $fieldType = $field->type->className();

    switch($fieldType) {
        case 'YourCustomFieldtype':
            return $this->formatCustom($value);
        default:
            return parent::formatValue($value, $field);
    }
}
```

## API Reference

```php
// Constructor
$processor = new MatrixDataProcessor(Page $page, array $options = []);

// Get all items from a matrix field
$items = $processor->getItems(string $matrixFieldName = 'matrix');
```

## Troubleshooting

**Class "MatrixDataProcessor" not found**  
Add `<?php namespace ProcessWire;` at the top of your template file.

**No items returned**  
- Verify the matrix field name: `$processor->getItems('your_field_name')`
- Check that items are published and the field has content

**type returns "unknown"**  
- Ensure each matrix type template has a `FieldtypeMatrixType` field added to it
- Open the field in Admin → Fields → Details and fill in the Matrix Type Identifier

## Requirements

- ProcessWire 3.0+
- PHP 8.1+

## License

MIT — free to use and modify

---

**Author:** Maxim Semenov — maxim@smnv.org  
**Module Version:** 1.1.0
