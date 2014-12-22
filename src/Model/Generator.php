<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Door\Dev\Model;
/**
 * Description of Generator
 *
 * @author serginho
 */
class Generator {
	
	protected $basedir;
	
	protected $namespace;
		
	protected $mwb_filename;
	
	protected $model_extends = array();
	
	/**
	 * @param string $basedir
	 * @param string $namespace
	 * @param string $mwb_filename
	 */
	public function __construct($basedir,$namespace,$mwb_filename) {
		
		$this->basedir = $basedir;
		$this->namespace = $namespace;
		$this->mwb_filename = $mwb_filename;
		
	}
	
	public function set_model_extend($model_name, $class_name)
	{
		$this->model_extends[$model_name] = $class_name;
	}
	
	/**
	 * @param array $values model_name => class_name
	 */
	public function set_model_extend_array(array $values)
	{
		foreach($values as $model_name => $class_name)
		{
			$this->set_model_extend($model_name, $class_name);
		}
	}	
	
	public function generate()
	{
		
	}
	
}
