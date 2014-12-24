<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Door\Dev\Model;
use MwbExporter\Formatter\Doctrine2\Annotation\Formatter as BaseFormatter;
/**
 * Description of MysqlWorkbenchFormatter
 *
 * @author serginho
 */
class GeneratorFormatter extends BaseFormatter{
	
	public function __construct($name = null) {
		
		$this->addConfigurations(array(
			Generator::CFG_DEFAULT_EXTEND_CLASSNAME => "\\Door\\ORM\\Model",
			Generator::CFG_EXTEND_ClASSES => null
		));	
		
		parent::__construct($name);
		
	}
	
	public function createTable(\MwbExporter\Model\Base $parent, $node) {
		return new GeneratorTable($parent, $node);
	}
	
	
}
