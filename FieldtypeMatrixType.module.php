<?php namespace ProcessWire;

class FieldtypeMatrixType extends FieldtypeText {

    public static function getModuleInfo() {
        return array(
            'title' => 'Matrix Type Fieldtype',
            'version' => 109,
            'summary' => 'Stores matrix item type identifier'
        );
    }

    public function getInputfield(Page $page, Field $field) {
        return $this->modules->get('InputfieldMatrixType');
    }

    public function sanitizeValue(Page $page, Field $field, $value) {
        return $this->wire('sanitizer')->name($value);
    }
}