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
	
	public function createTable(\MwbExporter\Model\Base $parent, $node) {
		return new GeneratorTable($parent, $node);
	}
	
	
}
