<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Door\Dev\Model;
use MwbExporter\Formatter\Doctrine2\Annotation\Model\Table as BaseTable;
/**
 * Description of MysqlWorkbenchFormatter
 *
 * @author serginho
 */
class MysqlWorkbenchTable extends BaseTable{
	
	public function getNamespace($class = null, $absolute = true) {
		
		$this->getConfig()->get(Formatter::CFG_GENERATE_EXTENDABLE_ENTITY);
		
		return parent::getNamespace($class, $absolute);
	}
	
	public function getClassName($base = false) {
		return parent::getClassName($base);
	}
	
	public function getClassFileName($base = false) {
		return parent::getClassFileName($base);
	}
	
	public function getTableFileName($format = null, $vars = array()) {
		parent::getTableFileName($format, $vars);
	}
	
	public function getClassNameAsArray()
	{
		
	}
	
    public function beautify($underscored_text)
    {
        return ucfirst(preg_replace_callback('@\_(\w)@', function($matches) {
            return "\\".ucfirst($matches[1]);
        }, $underscored_text));
    }	
	
}
