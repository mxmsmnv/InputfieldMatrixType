<?php namespace ProcessWire;

/**
 * Matrix Data Processor
 * Extracts and structures data from repeater matrix items
 */
class MatrixDataProcessor {
    
    protected $page;
    protected $skipFields = [
        'repeater_matrix_type',
        'id', 'name', 'parent', 'template',
        'created', 'modified', 'createdUser', 'modifiedUser',
        'price', 'sku' // Skip price and sku as they're handled separately
    ];
    
    public function __construct($page) {
        $this->page = $page;
    }
    
    /**
     * Get all matrix items as structured array
     */
    public function getItems($matrixFieldName = 'matrix') {
        $items = [];
        $matrixField = $this->page->$matrixFieldName;
        
        if(!$matrixField || !$matrixField->count()) {
            return $items;
        }
        
        foreach($matrixField as $item) {
            $items[] = $this->processItem($item);
        }
        
        return $items;
    }
    
    /**
     * Process single matrix item
     */
    protected function processItem($item) {
        $matrixInfo = $this->getMatrixTypeInfo($item);
        
        return [
            'id' => $item->id,
            'type' => $matrixInfo['type'],
            'displayName' => $matrixInfo['displayName'],
            'sku' => $item->sku ?: '',
            'price' => $item->price ?: null,
            'fields' => $this->getFields($item)
        ];
    }
    
    /**
     * Get matrix type info from FieldtypeMatrixType fields
     */
    protected function getMatrixTypeInfo($item) {
        $info = [
            'type' => 'unknown',
            'displayName' => 'Matrix'
        ];
        
        if(!$item->template || !$item->template->fields) {
            return $info;
        }

        // STRATEGY 0 (HIGHEST PRIORITY): Read repeater_matrix_type integer.
        // ProcessWire RepeaterMatrix stores the active type as an integer ID in the
        // system field 'repeater_matrix_type'. Map it to a type name via getMatrixTypes()
        // on the parent matrix field. This is 100% reliable for standard setups.
        $rmTypeId = (int)$item->get('repeater_matrix_type');
        if($rmTypeId > 0) {
            // Find the parent RepeaterMatrix field by looking at the item's parent page tree
            $parentField = null;
            // Template name for matrix items follows pattern: repeater_FIELDNAME
            if(preg_match('/^repeater_(.+)$/', $item->template->name, $m)) {
                $parentField = wire('fields')->get($m[1]);
            }
            if($parentField && $parentField->type instanceof FieldtypeRepeaterMatrix) {
                $matrixTypes = $parentField->type->getMatrixTypes($parentField);
                // getMatrixTypes() returns ['typeName' => typeId, ...] e.g. ['photo' => 1, 'em_video' => 2]
                $foundTypeName = null;
                foreach($matrixTypes as $typeName => $typeId) {
                    if((int)$typeId === $rmTypeId) {
                        $foundTypeName = (string)$typeName;
                        break;
                    }
                }
                if($foundTypeName !== null) {
                    // Get display name from the matching FieldtypeMatrixType field
                    foreach($item->template->fields as $field) {
                        if(!($field->type instanceof FieldtypeMatrixType)) continue;
                        $inp = $field->getInputfield($item);
                        if($inp && $inp->matrixTypeName === $foundTypeName) {
                            $info['type']        = $foundTypeName;
                            $info['displayName'] = $inp->matrixDisplayName ?: $this->prettifyName($field->name);
                            return $info;
                        }
                    }
                    // Fallback: type name without display name lookup
                    $info['type']        = $foundTypeName;
                    $info['displayName'] = $this->prettifyName($foundTypeName);
                    return $info;
                }
            }
        }
        
        // Collect all FieldtypeMatrixType fields with their configurations
        $matrixTypeFields = [];
        foreach($item->template->fields as $field) {
            if($field->type instanceof FieldtypeMatrixType) {
                $inputfield = $field->getInputfield($item);
                if($inputfield && $inputfield->matrixTypeName) {
                    $matrixTypeFields[] = [
                        'field' => $field,
                        'inputfield' => $inputfield,
                        'typeName' => $inputfield->matrixTypeName,
                        'displayName' => $inputfield->matrixDisplayName ?: $this->prettifyName($field->name)
                    ];
                }
            }
        }
        
        // If no matrix type fields found, return unknown
        if(empty($matrixTypeFields)) {
            if($item->template->label) {
                $info['displayName'] = $item->template->label;
                $info['type'] = $this->sanitizeName($item->template->name);
            }
            return $info;
        }
        
        // STRATEGY 1: Check FieldtypeMatrixType field values
        foreach($matrixTypeFields as $mtf) {
            $value = $item->get($mtf['field']->name);
            if($value && trim($value) !== '') {
                $info['type'] = $mtf['typeName'];
                $info['displayName'] = $mtf['displayName'];
                return $info;
            }
        }
        
        // STRATEGY 2: Use first matrix type field as final fallback
        if(!empty($matrixTypeFields)) {
            $info['type'] = $matrixTypeFields[0]['typeName'];
            $info['displayName'] = $matrixTypeFields[0]['displayName'];
        }
        
        return $info;
    }
    
    /**
     * Prettify field name for display
     */
    protected function prettifyName($name) {
        // Remove common prefixes
        $name = preg_replace('/^(matrix_|type_|repeater_)/', '', $name);
        
        // Convert to Title Case
        $name = str_replace('_', ' ', $name);
        $name = ucwords($name);
        
        return $name;
    }
    
    /**
     * Sanitize name for type identifier
     */
    protected function sanitizeName($name) {
        $name = str_replace('repeater_', '', $name);
        return wire('sanitizer')->name($name);
    }
    
    /**
     * Get all fields with values
     */
    protected function getFields($item) {
        $fields = [];
        
        if(!$item->template || !$item->template->fields) {
            return $fields;
        }
        
        foreach($item->template->fields as $field) {
            $fieldName = $field->name;
            
            // Skip system fields and FieldtypeMatrixType fields
            if(in_array($fieldName, $this->skipFields) || 
               $field->type instanceof FieldtypeMatrixType) {
                continue;
            }
            
            $value = $item->$fieldName;
            
            // Skip empty values
            if($this->isEmpty($value)) {
                continue;
            }
            
            $fields[] = [
                'name' => $fieldName,
                'label' => $field->label ?: $this->prettifyName($fieldName),
                'type' => $field->type->className(),
                'value' => $this->formatValue($value, $field)
            ];
        }
        
        return $fields;
    }
    
    /**
     * Format value based on field type
     */
    protected function formatValue($value, $field) {
        $fieldType = $field->type->className();
        
        switch($fieldType) {
            case 'FieldtypeOptions':
                return $this->formatOptions($value);
            
            case 'FieldtypeCheckbox':
                return (bool)$value;
            
            case 'FieldtypeDecimal':
            case 'FieldtypeFloat':
                return (float)$value;
            
            case 'FieldtypeInteger':
                return (int)$value;
            
            case 'FieldtypePage':
                return $this->formatPages($value);
            
            case 'FieldtypeImage':
                return $this->formatImages($value);
            
            default:
                return (string)$value;
        }
    }
    
    /**
     * Format Options field
     */
    protected function formatOptions($value) {
        if($value instanceof WireArray && $value->count()) {
            $options = [];
            foreach($value as $option) {
                if(isset($option->title)) {
                    $options[] = $option->title;
                }
            }
            return $options;
        }
        
        if(is_object($value) && isset($value->title)) {
            return $value->title;
        }
        
        return null;
    }
    
    /**
     * Format Page reference
     */
    protected function formatPages($value) {
        if($value instanceof PageArray) {
            $pages = [];
            foreach($value as $p) {
                $pages[] = [
                    'id' => $p->id,
                    'title' => $p->title,
                    'url' => $p->url
                ];
            }
            return $pages;
        }
        
        if($value instanceof Page) {
            return [
                'id' => $value->id,
                'title' => $value->title,
                'url' => $value->url
            ];
        }
        
        return null;
    }
    
    /**
     * Format Images
     */
    protected function formatImages($value) {
        $images = [];
        $imageList = $value instanceof Pageimages ? $value : [$value];
        
        foreach($imageList as $img) {
            $images[] = [
                'url' => $img->url,
                'description' => $img->description,
                'width' => $img->width,
                'height' => $img->height
            ];
        }
        
        return $images;
    }
    
    /**
     * Check if value is empty
     * For checkboxes: 0 or false = empty, 1 or true = filled
     */
    protected function isEmpty($value) {
        if(is_null($value)) return true;
        if(is_bool($value)) return !$value;  // false = empty, true = filled
        if(is_int($value) && $value === 0) return true;  // 0 = empty for checkboxes
        if(is_string($value) && trim($value) === '') return true;
        if(is_object($value) && $value instanceof WireArray && !$value->count()) return true;
        if(is_array($value) && empty($value)) return true;
        return false;
    }
}