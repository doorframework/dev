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
	
	protected $init_scripts_filename = null;
	
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
	
	public function set_init_scripts_filename($filename)
	{
		$this->init_scripts_filename = $filename;
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
		
		$doc = $bootstrap->export($formatter, $this->mwb_filename, $this->basedir);		
		if($this->init_scripts_filename != null)
		{
			$this->export_init_script($doc);
		}		

		return $doc;
	}
	
	private function export_init_script(\MwbExporter\Model\Document $doc)
	{
		$init_array = array();
		
		foreach($doc->getPhysicalModel()->getCatalog()->getSchemas() as $schema)
		{
			/*@var $schema \MwbExporter\Model\Schema */
			foreach($schema->getTables() as $table)
			{
				/*@var $table GeneratorTable */
				$class_name = "\\".implode("\\", $table->getFullClassNameAsArray());
				$model_name = $table->getModelName();
				$init_array[$model_name] = $class_name;
			}
		}	
		
		$out = array();
		$out[] = "<?php";
		$out[] = "/*@var \$storage \\Door\\ORM\\Storage */";
		$out[] = "\$storage->register_models(".var_export($init_array, true).");";
		file_put_contents($this->init_scripts_filename, join("\n", $out));				
	}
	
}
