<?php


namespace Door\Dev\Model;
use \MwbExporter\Formatter\Doctrine2\Annotation\Formatter;

/**
 * Model generator for Door framework
 */
class Generator {
	
	const CFG_DEFAULT_EXTEND_CLASSNAME = "defaultExtendClassname";
	const CFG_EXTEND_ClASSES = "extendClasses";	
	
	protected $defaultExtendClassname = "\\Door\\ORM\\Model";
	
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
	
	public function set_default_extend_classname($class)
	{
		$this->defaultExtendClassname = $class;
	}
	
	public function generate()
	{
		$document = $this->export();

		if($document == null)
		{
			return false;
		}
		else
		{
			return true;
		}		
	}
	
	private function export()
	{
		$bootstrap = new \MwbExporter\Bootstrap();

		$formatter = new GeneratorFormatter();
		$formatter->setup(array(
			Formatter::CFG_ENTITY_NAMESPACE => $this->namespace,
			Formatter::CFG_BACKUP_FILE => false,
			self::CFG_EXTEND_ClASSES => $this->model_extends,
			self::CFG_DEFAULT_EXTEND_CLASSNAME => $this->defaultExtendClassname
		));

		return $bootstrap->export($formatter, $this->mwb_filename, $this->basedir);		
	}
	
}
