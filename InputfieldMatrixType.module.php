<?php namespace ProcessWire;

class InputfieldMatrixType extends Inputfield {

    public static function getModuleInfo() {
        return array(
            'title' => 'Matrix Type',
            'version' => 109,
            'summary' => 'Hidden field for identifying repeater matrix item types',
            'requires' => 'ProcessWire>=3.0.0',
            'icon' => 'tag'
        );
    }

    public function __construct() {
        parent::__construct();
        $this->set('matrixTypeName', '');
        $this->set('matrixDisplayName', '');
    }

    public function ___render() {
        $value = $this->attr('value');
        if(!$value && $this->matrixTypeName) {
            $value = $this->matrixTypeName;
            $this->attr('value', $value);
        }
        
        $displayName = $this->matrixDisplayName ?: $value;
        
        
        return "<input type='hidden' name='{$this->attr('name')}' value='{$value}' />" .
               "<div style=''>" .
               "{$displayName} <code>{$value}</code>" .
               "</div>";
               
    }

    public function ___processInput(WireInputData $input) {
        if($this->matrixTypeName) {
            $this->attr('value', $this->matrixTypeName);
        }
        return $this;
    }

    public function ___getConfigInputfields() {
        $inputfields = parent::___getConfigInputfields();
        
        $f = $this->modules->get('InputfieldText');
        $f->attr('name', 'matrixTypeName');
        $f->label = 'Matrix Type Identifier';
        $f->description = 'Unique identifier (e.g., box, handbag, bouquet)';
        $f->required = true;
        $f->attr('value', $this->matrixTypeName);
        $f->columnWidth = 50;
        $inputfields->add($f);

        $f = $this->modules->get('InputfieldText');
        $f->attr('name', 'matrixDisplayName');
        $f->label = 'Display Name';
        $f->description = 'Human-readable name (e.g., Box, Handbag / Purse)';
        $f->attr('value', $this->matrixDisplayName);
        $f->columnWidth = 50;
        $inputfields->add($f);

        return $inputfields;
    }
}