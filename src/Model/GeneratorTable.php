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
		
        $writer
            ->open($this->getClassFileName(true))
            ->write('<?php')
            ->write('')
            ->write('/**')
            ->write(' * This file generated automatically')
            ->write(' */')
            ->write('namespace %s;', $namespace)
            ->write('')
            ->writeCallback(function(WriterInterface $writer, Table $_this = null) {
                $_this->writeUsedClasses($writer);
            })
            ->write('/**')
            ->write(' * '.$this->getModelName())
            ->write(' *')			
            ->writeCallback(function(WriterInterface $writer, GeneratorTable $_this = null) {
                $_this->writePropertiesComments($writer);
            })
			->write(' */')
            ->write('class '.$this->getClassName() . " extends ". $this->getExtendedClass())
            ->write('{')
            ->indent()
                ->writeCallback(function(WriterInterface $writer, GeneratorTable $_this = null) {
                    $_this->writePreClassHandler($writer);
					
					$writer->write("protected function _init_model() { ");
					$_this->writeInitModel($writer);
					$writer->write("}");
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

    /**
     * Get the use class for ORM if applicable.
     *
     * @return string
     */
    protected function getOrmUse()
    {
		return '';
    }

    /**
     * Get used classes.
     *
     * @return array
     */
    protected function getUsedClasses()
    {
        $uses = array();
        if ($orm = $this->getOrmUse()) {
            $uses[] = $orm;
        }

        return $uses;
    }

    protected function getInheritanceDiscriminatorColumn()
    {
        $result = array();
        if ($column = trim($this->parseComment('discriminator'))) {
            $result['name'] = $column;
            foreach ($this->getColumns() as $col) {
                if ($column == $col->getColumnName()) {
                    $result['type'] = $this->getFormatter()->getDatatypeConverter()->getDataType($col->getColumnType());
                    break;
                }
            }
        } else {
            $result['name'] = 'discr';
            $result['type'] = 'string';
        }

        return $result;
    }

    protected function getInheritanceDiscriminatorMap()
    {
        return array('base' => $this->getClassName(true), 'extended' => $this->getClassName());
    }

    public function writeUsedClasses(WriterInterface $writer)
    {
        $this->writeUses($writer, $this->getUsedClasses());

        return $this;
    }

    public function writeExtendedUsedClasses(WriterInterface $writer)
    {
        $uses = array();
        if ($orm = $this->getOrmUse()) {
            $uses[] = $orm;
        }
        $uses[] = sprintf('%s\%s', $this->getEntityNamespace(), $this->getClassName(true));
        $this->writeUses($writer, $uses);

        return $this;
    }

    protected function writeUses(WriterInterface $writer, $uses = array())
    {
        if (count($uses)) {
            foreach ($uses as $use) {
                $writer->write('use %s;', $use);
            }
            $writer->write('');
        }

        return $this;
    }

    /**
     * Write pre class handler.
     *
     * @param \MwbExporter\Writer\WriterInterface $writer
     * @return \MwbExporter\Formatter\Doctrine2\Annotation\Model\Table
     */
    public function writePreClassHandler(WriterInterface $writer)
    {
        return $this;
    }

    public function writeVars(WriterInterface $writer)
    {
        $this->writeColumnsVar($writer);
        $this->writeRelationsVar($writer);
        $this->writeManyToManyVar($writer);

        return $this;
    }

    protected function writeColumnsVar(WriterInterface $writer)
    {
        foreach ($this->getColumns() as $column) {
            $column->writeVar($writer);
        }
    }

    protected function writeRelationsVar(WriterInterface $writer)
    {
        // 1 <=> N references
        foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                continue;
            }

            $targetEntity = $local->getOwningTable()->getModelName();
            $targetEntityFQCN = $local->getOwningTable()->getModelNameAsFQCN($local->getReferencedTable()->getEntityNamespace());
            $mappedBy = $local->getReferencedTable()->getModelName();
            $related = $local->getForeignM2MRelatedName();

            $this->getDocument()->addLog(sprintf('  Writing 1 <=> ? relation "%s"', $targetEntity));

            $annotationOptions = array(
                'targetEntity' => $targetEntityFQCN,
                'mappedBy' => lcfirst($this->getRelatedVarName($mappedBy, $related)),
                'cascade' => $this->getFormatter()->getCascadeOption($local->parseComment('cascade')),
                'fetch' => $this->getFormatter()->getFetchOption($local->parseComment('fetch')),
                'orphanRemoval' => $this->getFormatter()->getBooleanOption($local->parseComment('orphanRemoval')),
            );

            if ($local->isManyToOne()) {
                $this->getDocument()->addLog('  Relation considered as "1 <=> N"');

                $writer
                    ->write('/**')
                    ->write(' * '.$this->getAnnotation('OneToMany', $annotationOptions))
                    ->write(' * '.$this->getJoins($local))
                    ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($local) {
                        if (count($orders = $_this->getFormatter()->getOrderOption($local->parseComment('order')))) {
                            $writer
                                ->write(' * '.$_this->getAnnotation('OrderBy', array($orders)))
                            ;
                        }
                    })
                    ->write(' */')
                    ->write('protected $'.lcfirst($this->getRelatedVarName($targetEntity, $related, true)).';')
                    ->write('')
                ;
            } else {
                $this->getDocument()->addLog('  Relation considered as "1 <=> 1"');

                $annotationOptions['inversedBy'] = $annotationOptions['mappedBy'];
                $annotationOptions['mappedBy'] = null;

                $writer
                    ->write('/**')
                    ->write(' * '.$this->getAnnotation('OneToOne', $annotationOptions))
                    ->write(' * '.$this->getJoins($local))
                    ->write(' */')
                    ->write('protected $'.lcfirst($targetEntity).';')
                    ->write('')
                ;
            }
        }

        // N <=> 1 references
        foreach ($this->getAllForeignKeys() as $foreign) {
            if ($this->isForeignKeyIgnored($foreign)) {
                continue;
            }

            $targetEntity = $foreign->getReferencedTable()->getModelName();
            $targetEntityFQCN = $foreign->getReferencedTable()->getModelNameAsFQCN($foreign->getOwningTable()->getEntityNamespace());
            $inversedBy = $foreign->getOwningTable()->getModelName();
            $related = $this->getRelatedName($foreign);

            $this->getDocument()->addLog(sprintf('  Writing N <=> ? relation "%s"', $targetEntity));

            $annotationOptions = array(
                'targetEntity' => $targetEntityFQCN,
                'inversedBy' => $foreign->isUnidirectional() ? null : lcfirst($this->getRelatedVarName($inversedBy, $related, true)),
                'cascade' => $this->getFormatter()->getCascadeOption($foreign->parseComment('cascade')),
                'fetch' => $this->getFormatter()->getFetchOption($foreign->parseComment('fetch')),
            );

            if ($foreign->isManyToOne()) {
                $this->getDocument()->addLog('  Relation considered as "N <=> 1"');

                $writer
                    ->write('/**')
                    ->write(' * '.$this->getAnnotation('ManyToOne', $annotationOptions))
                    ->write(' * '.$this->getJoins($foreign, false))
                    ->write(' */')
                    ->write('protected $'.lcfirst($this->getRelatedVarName($targetEntity, $related)).';')
                    ->write('')
                ;
            } else {
                $this->getDocument()->addLog('  Relation considered as "1 <=> 1"');

                if (null !== $annotationOptions['inversedBy']) {
                    $annotationOptions['inversedBy'] = lcfirst($this->getRelatedVarName($inversedBy, $related));
                }
                $annotationOptions['cascade'] = $this->getFormatter()->getCascadeOption($foreign->parseComment('cascade'));

                $writer
                    ->write('/**')
                    ->write(' * '.$this->getAnnotation('OneToOne', $annotationOptions))
                    ->write(' * '.$this->getJoins($foreign, false))
                    ->write(' */')
                    ->write('protected $'.lcfirst($targetEntity).';')
                    ->write('')
                ;
            }
        }

        return $this;
    }

    protected function writeManyToManyVar(WriterInterface $writer)
    {
        foreach ($this->getTableM2MRelations() as $relation) {
            $this->getDocument()->addLog(sprintf('  Writing setter/getter for N <=> N "%s"', $relation['refTable']->getModelName()));

            $fk1 = $relation['reference'];
            $isOwningSide = $this->getFormatter()->isOwningSide($relation, $fk2);
            $annotationOptions = array(
                'targetEntity' => $relation['refTable']->getModelNameAsFQCN($this->getEntityNamespace()),
                'mappedBy' => null,
                'inversedBy' => lcfirst($this->getPluralModelName()),
                'cascade' => $this->getFormatter()->getCascadeOption($fk1->parseComment('cascade')),
                'fetch' => $this->getFormatter()->getFetchOption($fk1->parseComment('fetch')),
            );

            // if this is the owning side, also output the JoinTable Annotation
            // otherwise use "mappedBy" feature
            if ($isOwningSide) {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for N <=> N "%s"', "owner"));

                if ($fk1->isUnidirectional()) {
                    unset($annotationOptions['inversedBy']);
                }

                $writer
                    ->write('/**')
                    ->write(' * '.$this->getAnnotation('ManyToMany', $annotationOptions))
                    ->write(' * '.$this->getAnnotation('JoinTable',
                        array(
                            'name'               => $this->quoteIdentifier($relation['reference']->getOwningTable()->getRawTableName()),
                            'joinColumns'        => array($this->getJoins($fk1, false)),
                            'inverseJoinColumns' => array($this->getJoins($fk2, false)),
                        ), array('multiline' => true, 'wrapper' => ' * %s')))
                    ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($fk2) {
                        if (count($orders = $_this->getFormatter()->getOrderOption($fk2->parseComment('order')))) {
                            $writer
                                ->write(' * '.$_this->getAnnotation('OrderBy', array($orders)))
                            ;
                        }
                    })
                    ->write(' */')
                ;
            } else {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for N <=> N "%s"', "inverse"));

                if ($fk2->isUnidirectional()) {
                    continue;
                }

                $annotationOptions['mappedBy'] = $annotationOptions['inversedBy'];
                $annotationOptions['inversedBy'] = null;
                $writer
                    ->write('/**')
                    ->write(' * '.$this->getAnnotation('ManyToMany', $annotationOptions))
                    ->write(' */')
                ;
            }
            $writer
                ->write('protected $'.lcfirst($relation['refTable']->getPluralModelName()).';')
                ->write('')
            ;
        }

        return $this;
    }

    public function writeConstructor(WriterInterface $writer)
    {
        $writer
            ->write('public function __construct()')
            ->write('{')
            ->indent()
                ->writeCallback(function(WriterInterface $writer, Table $_this = null) {
                    $_this->writeRelationsConstructor($writer);
                    $_this->writeManyToManyConstructor($writer);
                })
            ->outdent()
            ->write('}')
            ->write('')
        ;

        return $this;
    }

    public function writeRelationsConstructor(WriterInterface $writer)
    {
        foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                continue;
            }
            $this->getDocument()->addLog(sprintf('  Writing N <=> 1 constructor "%s"', $local->getOwningTable()->getModelName()));

            $related = $local->getForeignM2MRelatedName();
            $writer->write('$this->%s = new %s();', lcfirst($this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, true)), $this->getCollectionClass(false));
        }
    }

    public function writeManyToManyConstructor(WriterInterface $writer)
    {
        foreach ($this->getTableM2MRelations() as $relation) {
            $this->getDocument()->addLog(sprintf('  Writing M2M constructor "%s"', $relation['refTable']->getModelName()));
            $writer->write('$this->%s = new %s();', lcfirst($relation['refTable']->getPluralModelName()), $this->getCollectionClass(false));
        }
    }

    public function writeGetterAndSetter(WriterInterface $writer)
    {
        $this->writeColumnsGetterAndSetter($writer);
        $this->writeRelationsGetterAndSetter($writer);
        $this->writeManyToManyGetterAndSetter($writer);

        return $this;
    }

    protected function writeColumnsGetterAndSetter(WriterInterface $writer)
    {
        foreach ($this->getColumns() as $column) {
            $column->writeGetterAndSetter($writer);
        }
    }

    protected function writeRelationsGetterAndSetter(WriterInterface $writer)
    {
        // N <=> 1 references
        foreach ($this->getAllLocalForeignKeys() as $local) {
            if ($this->isLocalForeignKeyIgnored($local)) {
                continue;
            }

            $this->getDocument()->addLog(sprintf('  Writing setter/getter for N <=> ? "%s"', $local->getParameters()->get('name')));

            if ($local->isManyToOne()) {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for "%s"', 'N <=> 1'));

                $related = $local->getForeignM2MRelatedName();
                $related_text = $local->getForeignM2MRelatedName(false);

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Add '.trim($local->getOwningTable()->getModelName().' entity '.$related_text). ' to collection (one to many).')
                    ->write(' *')
                    ->write(' * @param '.$local->getOwningTable()->getNamespace().' $'.lcfirst($local->getOwningTable()->getModelName()))
                    ->write(' * @return '.$this->getNamespace())
                    ->write(' */')
                    ->write('public function add'.$this->getRelatedVarName($local->getOwningTable()->getModelName(), $related).'('.$local->getOwningTable()->getModelName().' $'.lcfirst($local->getOwningTable()->getModelName()).')')
                    ->write('{')
                    ->indent()
                        ->write('$this->'.lcfirst($this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, true)).'[] = $'.lcfirst($local->getOwningTable()->getModelName()).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // remover
                    ->write('/**')
                    ->write(' * Remove '.trim($local->getOwningTable()->getModelName().' entity '.$related_text). ' from collection (one to many).')
                    ->write(' *')
                    ->write(' * @param '.$local->getOwningTable()->getNamespace().' $'.lcfirst($local->getOwningTable()->getModelName()))
                    ->write(' * @return '.$this->getNamespace())
                    ->write(' */')
                    ->write('public function remove'.$this->getRelatedVarName($local->getOwningTable()->getModelName(), $related).'('.$local->getOwningTable()->getModelName().' $'.lcfirst($local->getOwningTable()->getModelName()).')')
                    ->write('{')
                    ->indent()
                        ->write('$this->'.lcfirst($this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, true)).'->removeElement($'.lcfirst($local->getOwningTable()->getModelName()).');')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.trim($local->getOwningTable()->getModelName().' entity '.$related_text).' collection (one to many).')
                    ->write(' *')
                    ->write(' * @return '.$this->getCollectionInterface())
                    ->write(' */')
                    ->write('public function get'.$this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, true).'()')
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.lcfirst($this->getRelatedVarName($local->getOwningTable()->getModelName(), $related, true)).';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            } else {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for "%s"', '1 <=> 1'));

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.$local->getReferencedTable()->getModelName().' entity (one to one).')
                    ->write(' *')
                    ->write(' * @param '.$local->getReferencedTable()->getNamespace().' $'.lcfirst($local->getReferencedTable()->getModelName()))
                    ->write(' * @return '.$this->getNamespace())
                    ->write(' */')
                    ->write('public function set'.$local->getReferencedTable()->getModelName().'('.$local->getReferencedTable()->getModelName().' $'.lcfirst($local->getReferencedTable()->getModelName()).' = null)')
                    ->write('{')
                    ->indent()
                        ->writeIf(!$local->isUnidirectional(), '$'.lcfirst($local->getReferencedTable()->getModelName()).'->set'.$local->getOwningTable()->getModelName().'($this);')
                        ->write('$this->'.lcfirst($local->getReferencedTable()->getModelName()).' = $'.lcfirst($local->getReferencedTable()->getModelName()).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.$local->getReferencedTable()->getModelName().' entity (one to one).')
                    ->write(' *')
                    ->write(' * @return '.$local->getReferencedTable()->getNamespace())
                    ->write(' */')
                    ->write('public function get'.$local->getReferencedTable()->getModelName().'()')
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.lcfirst($local->getReferencedTable()->getModelName()).';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            }
        }

        // 1 <=> N references
        foreach ($this->getAllForeignKeys() as $foreign) {
            if ($this->isForeignKeyIgnored($foreign)) {
                continue;
            }

            $this->getDocument()->addLog(sprintf('  Writing setter/getter for 1 <=> ? "%s"', $foreign->getParameters()->get('name')));

            if ($foreign->isManyToOne()) {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for "%s"', '1 <=> N'));

                $related = $this->getRelatedName($foreign);
                $related_text = $this->getRelatedName($foreign, false);

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.trim($foreign->getReferencedTable()->getModelName().' entity '.$related_text).' (many to one).')
                    ->write(' *')
                    ->write(' * @param '.$foreign->getReferencedTable()->getNamespace().' $'.lcfirst($foreign->getReferencedTable()->getModelName()))
                    ->write(' * @return '.$this->getNamespace())
                    ->write(' */')
                    ->write('public function set'.$this->getRelatedVarName($foreign->getReferencedTable()->getModelName(), $related).'('.$foreign->getReferencedTable()->getModelName().' $'.lcfirst($foreign->getReferencedTable()->getModelName()).' = null)')
                    ->write('{')
                    ->indent()
                        ->write('$this->'.lcfirst($this->getRelatedVarName($foreign->getReferencedTable()->getModelName(), $related)).' = $'.lcfirst($foreign->getReferencedTable()->getModelName()).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.trim($foreign->getReferencedTable()->getModelName().' entity '.$related_text).' (many to one).')
                    ->write(' *')
                    ->write(' * @return '.$foreign->getReferencedTable()->getNamespace())
                    ->write(' */')
                    ->write('public function get'.$this->getRelatedVarName($foreign->getReferencedTable()->getModelName(), $related).'()')
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.lcfirst($this->getRelatedVarName($foreign->getReferencedTable()->getModelName(), $related)).';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            } else {
                $this->getDocument()->addLog(sprintf('  Applying setter/getter for "%s"', '1 <=> 1'));

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.$foreign->getReferencedTable()->getModelName().' entity (one to one).')
                    ->write(' *')
                    ->write(' * @param '.$foreign->getReferencedTable()->getNamespace().' $'.lcfirst($foreign->getReferencedTable()->getModelName()))
                    ->write(' * @return '.$this->getNamespace())
                    ->write(' */')
                    ->write('public function set'.$foreign->getReferencedTable()->getModelName().'('.$foreign->getReferencedTable()->getModelName().' $'.lcfirst($foreign->getReferencedTable()->getModelName()).')')
                    ->write('{')
                    ->indent()
                        ->write('$this->'.lcfirst($foreign->getReferencedTable()->getModelName()).' = $'.lcfirst($foreign->getReferencedTable()->getModelName()).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.$foreign->getReferencedTable()->getModelName().' entity (one to one).')
                    ->write(' *')
                    ->write(' * @return '.$foreign->getReferencedTable()->getNamespace())
                    ->write(' */')
                    ->write('public function get'.$foreign->getReferencedTable()->getModelName().'()')
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.lcfirst($foreign->getReferencedTable()->getModelName()).';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            }
        }

        return $this;
    }

    protected function writeManyToManyGetterAndSetter(WriterInterface $writer)
    {
        foreach ($this->getTableM2MRelations() as $relation) {
            $this->getDocument()->addLog(sprintf('  Writing N <=> N relation "%s"', $relation['refTable']->getModelName()));

            $isOwningSide = $this->getFormatter()->isOwningSide($relation, $fk2);
            $writer
                ->write('/**')
                ->write(' * Add '.$relation['refTable']->getModelName().' entity to collection.')
                ->write(' *')
                ->write(' * @param '. $relation['refTable']->getNamespace().' $'.lcfirst($relation['refTable']->getModelName()))
                ->write(' * @return '.$this->getNamespace($this->getModelName()))
                ->write(' */')
                ->write('public function add'.$relation['refTable']->getModelName().'('.$relation['refTable']->getModelName().' $'.lcfirst($relation['refTable']->getModelName()).')')
                ->write('{')
                ->indent()
                    ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($isOwningSide, $relation) {
                        if ($isOwningSide) {
                            $writer->write('$%s->add%s($this);', lcfirst($relation['refTable']->getModelName()), $_this->getModelName());
                        }
                    })
                    ->write('$this->'.lcfirst($relation['refTable']->getPluralModelName()).'[] = $'.lcfirst($relation['refTable']->getModelName()).';')
                    ->write('')
                    ->write('return $this;')
                ->outdent()
                ->write('}')
                ->write('')
                ->write('/**')
                ->write(' * Remove '.$relation['refTable']->getModelName().' entity from collection.')
                ->write(' *')
                ->write(' * @param '. $relation['refTable']->getNamespace().' $'.lcfirst($relation['refTable']->getModelName()))
                ->write(' * @return '.$this->getNamespace($this->getModelName()))
                ->write(' */')
                ->write('public function remove'.$relation['refTable']->getModelName().'('.$relation['refTable']->getModelName().' $'.lcfirst($relation['refTable']->getModelName()).')')
                ->write('{')
                ->indent()
                    ->writeCallback(function(WriterInterface $writer, Table $_this = null) use ($isOwningSide, $relation) {
                        if ($isOwningSide) {
                            $writer->write('$%s->remove%s($this);', lcfirst($relation['refTable']->getModelName()), $_this->getModelName());
                        }
                    })
                    ->write('$this->'.lcfirst($relation['refTable']->getPluralModelName()).'->removeElement($'.lcfirst($relation['refTable']->getModelName()).');')
                    ->write('')
                    ->write('return $this;')
                ->outdent()
                ->write('}')
                ->write('')
                ->write('/**')
                ->write(' * Get '.$relation['refTable']->getModelName().' entity collection.')
                ->write(' *')
                ->write(' * @return '.$this->getCollectionInterface())
                ->write(' */')
                ->write('public function get'.$relation['refTable']->getPluralModelName().'()')
                ->write('{')
                ->indent()
                    ->write('return $this->'.lcfirst($relation['refTable']->getPluralModelName()).';')
                ->outdent()
                ->write('}')
                ->write('')
            ;
        }

        return $this;
    }

    /**
     * Write post class handler.
     *
     * @param \MwbExporter\Writer\WriterInterface $writer
     * @return \MwbExporter\Formatter\Doctrine2\Annotation\Model\Table
     */
    public function writePostClassHandler(WriterInterface $writer)
    {
        return $this;
    }

    public function writeSerialization(WriterInterface $writer)
    {
        $writer
            ->write('public function __sleep()')
            ->write('{')
            ->indent()
                ->write('return array(%s);', implode(', ', array_map(function($column) {
                    return sprintf('\'%s\'', $column);
                }, $this->getColumns()->getColumnNames())))
            ->outdent()
            ->write('}')
        ;

        return $this;
    }
	
	public function getFullClassNameAsArray($base = false)
	{
		$table_name_array = $this->getClassNameAsArray($base);
		$namespace_array = explode("\\", trim($this->getEntityNamespace(), "\\"));
		return $namespace_array + $table_name_array;		
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
		return "\\Door\\ORM\\Model";
	}
	
	public function writePropertiesComments(Writer $writer)
	{
		$converter = $this->getFormatter()->getDatatypeConverter();		
		
        foreach ($this->getColumns() as $column) {
			/* @var $column MwbExporter\Model\Column */
			$name = $column->getName();
			$comment = trim($column->getComment());
			$nativeType = $converter->getNativeType($converter->getMappedType($column));		
			$writer->write(" * @param {$nativeType} \${$name}");			            
			$writer->writeIf($comment, $comment);
        }		
	}
	
	public function writeInitModel(Writer $writer)
	{
		$writer->indent();						
		$table_columns = var_export($this->getTableColumnsArray(), true);		
		$writer->write("$this->_table_columns = {$table_columns};");
		$writer->outdent();
	}
	
	public function getTableColumnsArray()
	{
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
		
}
