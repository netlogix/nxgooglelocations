<?php

namespace Netlogix\Nxgooglelocations\ViewHelpers\Be;

class TableListViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\Be\TableListViewHelper
{
    public function render()
    {
        $result = parent::render();
        return is_int(strpos($result, '<table')) ? $result : $this->renderChildren();
    }
}
