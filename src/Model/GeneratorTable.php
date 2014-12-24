<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Door\Dev\Model;
use MwbExporter\Formatter\Doctrine2\Model\Table;
use MwbExporter\Writer\Writer;
use MwbExporter\Writer\WriterInterface;
use MwbExporter\Formatter\Doctrine2\Annotation\Formatter;
use Doctrine\Common\Inflector\Inflector;
/**
 * Description of MysqlWorkbenchFormatter
 *
 * @author serginho
 */
class GeneratorTable extends Table{
	
    
    public function writeTable(WriterInterface $writer)
    {
        switch (true) {
            case $this->isExternal():
                return self::WRITE_EXTERNAL;

            case $this->getConfig()->get(Formatter::CFG_SKIP_M2M_TABLES) && $this->isManyToMany():
                return self::WRITE_M2M;

            default:
                $this->writeEntity($writer);
                return self::WRITE_OK;
        }
    }

    protected function writeEntity(WriterInterface $writer)
    {
        $this->getDocument()->addLog(sprintf('Writing table "%s"', $this->getModelName()));

        $namespace = $this->getEntityNamespace(true);
		$table = $this->getRawTableName();
		$primary_key = $this->getPrimaryKey();
		$model_name = $this->getModelName();
		
		if($primary_key == null)
		{
			return;
		}
	
		
        $writer
            ->open($this->getClassFileName(true))
            ->write('<?php')
            ->write('')
            ->write('/**')
            ->write(' * This file generated automatically. You should not change this file.')
            ->write(' */')
            ->write('namespace %s;', $namespace)
            ->write('/**')
            ->write(' * '.$model_name)
            ->write(' *')			
            ->writeCallback(function(WriterInterface $writer, GeneratorTable $_this = null) {
                $_this->writePropertiesComments($writer);
            })
			->write(' */')
            ->write('class '.$this->getClassName() . " extends ". $this->getExtendedClass())
            ->write('{')
            ->indent()
				->write("protected \$_table_name = '{$table}';")
				->write("protected \$_primary_key = '{$primary_key}';")
				->write("protected \$_object_name = '{$model_name}';")
				->write('')
                ->writeCallback(function(WriterInterface $writer, GeneratorTable $_this = null) {                   					
					$_this->writeInitModel($writer);					
                })
            ->outdent()
            ->write('}')
            ->close();
				
				
        if ( ! $writer->getStorage()->hasFile($this->getClassFileName())) {
			
			$namespace = $this->getEntityNamespace();
			
            $writer
                ->open($this->getClassFileName())
                ->write('<?php')
                ->write('')
                ->write('namespace %s;', $namespace)
                ->write('')
                ->write('/**')
                ->write(' * '.$this->getModelName())
                ->write(' */')
                ->write('class %s extends %s', $this->getClassName(), "\\" . $this->getEntityNamespace(true) . "\\" . $this->getClassName(true))
                ->write('{')
                ->write('}')
                ->close()
            ;
        }
    }

    /**
     * Get the generated class name.
     *
     * @param bool $base
     * @return string
     */
    protected function getClassFileName($base = false)
    {
		return implode("/", $this->getClassNameAsArray($base)).".php";
    }

    /**
     * Get the generated class name.
     *
     * @param bool $base
     * @return string
     */
    protected function getClassName($base = false)
    {
		$arr = $this->getClassNameAsArray($base);
		return $arr[count($arr) - 1];
    }

    public function writeVars(WriterInterface $writer)
    {
        $this->writeColumnsVar($writer);
        $this->writeRelationsVar($writer);
        $this->writeManyToManyVar($writer);

        return $this;
    }
	
	public function getFullClassNameAsArray($base = false)
	{
		$table_name_array = $this->getClassNameAsArray($base);
		$namespace_array = explode("\\", trim($this->getEntityNamespace(), "\\"));
		array_push($namespace_array, $table_name_array[count($table_name_array) - 1]);				
		return $namespace_array;		
	}
	
	public function getClassNameAsArray($base = false)
	{
		$table_name_array = array_map('ucfirst', explode("_", $this->getRawTableName()));
		if($base)
		{
			array_unshift($table_name_array, "Base");
		}
		$table_name_array[count($table_name_array) - 1] = 
				ucfirst(Inflector::singularize($table_name_array[count($table_name_array) - 1]));
		return $table_name_array;
	}
	
    public function getModelName()
    {
        return implode("_", $this->getClassNameAsArray());
    }	
		
	
    /**
     * Get the entity namespace.
     *
     * @return string
     */
    public function getEntityNamespace($base = false)
    {
        $namespace = '';
        if (($bundleNamespace = $this->parseComment('bundleNamespace')) || ($bundleNamespace = $this->getConfig()->get(Formatter::CFG_BUNDLE_NAMESPACE))) {
            $namespace = $bundleNamespace.'\\';
        }
        if ($entityNamespace = $this->getConfig()->get(Formatter::CFG_ENTITY_NAMESPACE)) {
            $namespace .= $entityNamespace;
        } else {
            $namespace .= 'Entity';
        }
		
		if($base)
		{
			$namespace .= "\\Base";
		}
		
		$class_arr = $this->getClassNameAsArray();
		if(count($class_arr) > 1)
		{		
			$namespace .= "\\".implode("\\", array_slice($class_arr, 0, count($class_arr) - 1));
		}

        return $namespace;
    }	
	
	public function getExtendedClass()
	{
		$return_value = null;
		
		$current_model = $this->getModelName();
		$model_extends_config = $this->getConfig()->get(Generator::CFG_EXTEND_ClASSES);
		$default_model_config = $this->getConfig()->get(Generator::CFG_DEFAULT_EXTEND_CLASSNAME);
		
		if(is_array($model_extends_config) && isset($model_extends_config[$current_model]))
		{
			$return_value = $model_extends_config[$current_model];
		}
		elseif($default_model_config != null)
		{
			$return_value = $default_model_config;
		}
		else
		{
			throw new Exeption("Default model config not found.");
		}
		
		return $return_value;
	}
	
	public function writePropertiesComments(Writer $writer)
	{
		$converter = $this->getFormatter()->getDatatypeConverter();		
		
        foreach ($this->getColumns() as $column) {
			/* @var $column MwbExporter\Model\Column */
			$name = $column->getName();
			$comment = trim($column->getComment()," \n\t\r*");
			$nativeType = $converter->getNativeType($converter->getMappedType($column));		
			$writer->write(" * @param {$nativeType} \${$name} $comment");			            
        }		
		
        foreach ($this->getAllForeignKeys() as $foreign) {
            if ($this->isForeignKeyIgnored($foreign)) {
                continue;
            }

			$localColumns = $foreign->getLocalColumns();
			if(count($localColumns) != 1)
			{
				continue;
			}
			
			$propertyName = preg_replace('/_[^_]*$/', "", $localColumns[0]);
			
            $targetEntity = "\\".implode("\\", $foreign->getReferencedTable()->getFullClassNameAsArray());			
			$writer->write(" * @param {$targetEntity} \${$propertyName}");           
        }		
		
		foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                continue;
            }
			$targetEntity = "\\".implode("\\", $local->getOwningTable()->getFullClassNameAsArray());			
			$localColumns = $local->getLocalColumns();			
			if(count($localColumns) != 1)
			{
				continue;
			}
			
			$this_table_singular = Inflector::singularize($this->getRawTableName());
			$target_table = $local->getOwningTable()->getRawTableName();			
			$property_name = preg_replace('/_[^_]*$/', "", $localColumns[0]);				
			$property_name = preg_replace("/{$this_table_singular}$/", $target_table, $property_name);
			$writer->write(" * @param {$targetEntity} \${$property_name}");
        }	
		
		foreach ($this->getTableM2MRelations() as $relation) {

            $fk1 = $relation['reference'];
			$refTable = $relation['refTable'];
			$propertyName = $refTable->getRawTableName();			
            $fk2 = $fk1->getOwningTable()->getRelationToTable($refTable->getRawTableName());
			$targetEntity = "\\".implode("\\", $refTable->getFullClassNameAsArray());	
			
			$writer->write(" * @param {$targetEntity} \${$propertyName}");		
        }		
	}
	
	public function writeInitModel(Writer $writer)
	{
		$writer->write("protected function _init_model() { ");
		$writer->indent();	
		$this->writeInitModelTableColumns($writer);
		$writer->outdent();	
		$writer->write("}");
	}
	
	public function writeInitModelTableColumns(Writer $writer)
	{							
		$table_columns = $this->export_var($this->getTableColumnsArray());
		$has_many = $this->export_var($this->getHasManyArray());
		$has_one = $this->export_var($this->getHasOneArray());
		$writer->write("\$this->_table_columns = {$table_columns};");			
		$writer->write("\$this->_has_many = {$has_many};");			
		$writer->write("\$this->_has_one = {$has_one};");			
	}
	
	protected function export_var(array $values)
	{
		$text = str_replace(array("\r","\n"), "", var_export($values, true));
		return preg_replace("/[ ]{2,100}/", " ", $text);
	}
	
	public function getTableColumnsArray()
	{
		$converter = $this->getFormatter()->getDatatypeConverter();		
		$return_value = array();
		
        foreach ($this->getColumns() as $column) {
			/* @var $column MwbExporter\Model\Column */
			$name = $column->getName();
			$nativeType = $converter->getNativeType($converter->getMappedType($column));		
			$return_value[$name] = array(
				'name' => $name,
				'type' => $nativeType
			);
        }				
		return $return_value;
	}
	
	protected function getHasManyArray()
	{
		$return_value = array();
		
		foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                continue;
            }

            $targetEntity = $local->getOwningTable()->getModelName();
			$localColumns = $local->getLocalColumns();
			
			if(count($localColumns) != 1)
			{
				continue;
			}
			
			$this_table_singular = Inflector::singularize($this->getRawTableName());
			$target_table = $local->getOwningTable()->getRawTableName();			
			$property_name = preg_replace('/_[^_]*$/', "", $localColumns[0]);				
			$property_name = preg_replace("/{$this_table_singular}$/", $target_table, $property_name);						
			
			$return_value[$property_name] = array(
				'model' => $targetEntity,
				'foreign_key' => $localColumns[0]
			);
					
        }		
		
		foreach ($this->getTableM2MRelations() as $relation) {

            $fk1 = $relation['reference'];
			$refTable = $relation['refTable'];
			$propertyName = $refTable->getRawTableName();			
            $fk2 = $fk1->getOwningTable()->getRelationToTable($refTable->getRawTableName());
			$targetModel = $refTable->getModelName();
			
			$foreigns1 = $fk1->getLocalColumns();
			$foreigns2 = $fk2->getLocalColumns();
			
			$return_value[$propertyName] = array(
				"model" => $targetModel,
				"throught" => $fk1->getOwningTable()->getRawTableName(),
				"far_key" => $foreigns2[0],
				"foreign_key" => $foreigns1[0]
			);			
        }
		
		
		return $return_value;
	}
		
	protected function getHasOneArray()
	{
		$return_value = array();
		
        foreach ($this->getAllForeignKeys() as $foreign) {
            if ($this->isForeignKeyIgnored($foreign)) {
                continue;
            }

			$localColumns = $foreign->getLocalColumns();
			if(count($localColumns) != 1)
			{
				continue;
			}
			
			$propertyName = preg_replace('/_[^_]*$/', "", $localColumns[0]);
					
			
            $targetEntity = $foreign->getReferencedTable()->getModelName();			
			$return_value[$propertyName] = array(
				'model' => $targetEntity,
				'foreign_key' => $localColumns[0]
			);			
        }			
		
		return $return_value;
	}
	
	public function getPrimaryKey()
	{
		$return_value = null;
		
        foreach ($this->getColumns() as $column) {			
			/* @var $column \MwbExporter\Model\Column */
			if($column->isPrimary())
			{
				if($return_value == null)
				{
					$return_value = $column->getName();
				}
				else
				{
					//return null if there is two primary keys
					return null;
				}
			}
        }		
		
		return $return_value;
	}
		
}
