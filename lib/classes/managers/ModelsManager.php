<?php
/**
 * Models Manager
 */
/**
 * Models Manager Class
 * @package WebLauncher\Managers
 */
class ModelsManager
{
	/**
	 * @var Database Connection
	 */
	var $db;
	/**
	 * @var Loaded Libraries
	 */
	var $models=array();	

	/**
	 * Constructor
	 * @return
	 */
	function __construct()
	{
	}
	/**
	 * Get magic method
	 * @param string $name
	 * @example $this->model Inits model named model
	 */
	function __get($name)
	{
		if($this->import($name))
			return $this->$name;
		else
		{
			$trace = debug_backtrace();
	        System::triggerError(
	            'Undefined table for model in php.config.php file via __get(): ' . $name .
	            ' in ' . $trace[0]['file'] .
	            ' on line ' . $trace[0]['line'],
	            E_USER_NOTICE);
	        return null;
		}
	}
	/**
	 * Magic method call inits new model
	 * @param string $name
	 * @param array $arguments
	 */
	function __call($name,$arguments){
		if($this->import($name)){
			return $this->$name;
		}
		else
		{
			$trace = debug_backtrace();
	        System::triggerError(
	            'Undefined table for model in php.config.php file via __call(): ' . $name .
	            ' in ' . $trace[0]['file'] .
	            ' on line ' . $trace[0]['line'],
	            E_USER_NOTICE);
	        return null;
		}
	}

	/**
	 * Import particular libraries from the lib folder
	 * @param object $model
	 * @return
	 */
	function import($model)
	{
		global $page;
		if(!in_array($model,$this->models))
		{
			if($this->import_from_page($model))
				return true;
			if(isset($this->db->tables[$model]))
			{
				$this->$model=new Base();                
				if(is_a($this->$model,'_Base') && !$this->$model->table)
					$this->$model->table=$this->db->tables[strtolower($model)];                
				$this->models[]=$model;
                $this->{$model}->models=&$this;
				$this->{$model}->system=&$page;
				return true;
			}
			return false;
		}
		else
		{
			return true;
		}
	}
	
	/**
	 * Import model from file
	 * @param string $model
	 * @param string $file
	 */
	function import_from_file($model,$file)
	{
		if(!class_exists($model) && is_file($file))
		{
			require_once $file;
			$model_name=strtolower($model);
			$this->$model_name=new $model();
			if(is_a($this->$model_name,'_Base') && !$this->$model_name->table)
				$this->$model_name->table=$this->db->tables[strtolower($model)];
			$this->models[]=strtolower($model);
            $this->{$model_name}->models=&$this;
            global $page;
            $this->{$model_name}->system=&$page;
			return true;
		}
		return false;
	}
	
	/**
	 * Import from component
	 * @param string $model
	 */
	function import_from_page($model)
	{
		global $page;

		// page subpaths
		$paths=array();
		$spath=$page->paths['root_dir'].$page->modules_folder.DS;		
		foreach($page->subquery as $k=>$v)
		{
			if($v)
			{
				if($k>0)
				$spath.='components'.DS.$v;
				else $spath.=$v;

				if($spath[strlen($spath)-1]!=DS)
					$spath.=DS;

				$paths[]=$spath;
			}
		}
		foreach(array_reverse($paths) as $v)
			if($this->import_from_file($model,$v.'models'.DS.$model.'.php') || $this->import_from_file($model,$v.'models'.DS.ucfirst($model).'.php'))
				return true;
		return false;
	}
}

?>