<?php
/**
 * ModuleFrontControllerOverride
 * 
 * @author Michel Courtade <michel@dwf.fr>
 * @version 0.1
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class ModuleFrontControllerOverride
{
  /**
   * The current module name to override
   * @var string
   */
  protected $module_name;
  
  /**
  * The current module controller name to override
  * @var string
  */
  protected $module_controller_name;

  /**
   * Path of module controller core (in modules/ dir)
   * @var string
   */
  protected $module_controller_core_path;

  /**
   * Filemtime of all overrided controller module
   * @var array
   */
  protected $overrided_module_controller;

  /**
   * Constructor
   * Init the constant and property
   * @param string $moduleName
   * @param string $moduleControllerName
   */
  protected function __construct($moduleName, $moduleControllerName)
  {
    $this->module_controller_name = $moduleControllerName;
    $this->module_name = $moduleName;

    if(!defined('_PS_THEME_CACHE_DIR_'))
      define('_PS_THEME_CACHE_DIR_', _PS_THEME_DIR_.'cache'.DS.'modules'.DS);

    if(!is_dir(_PS_THEME_CACHE_DIR_))
      mkdir(_PS_THEME_CACHE_DIR_, 0705);

    $this->module_controller_core_path = _PS_THEME_CACHE_DIR_.$this->module_name."controllers_front_".$this->module_controller_name.'.core.php';

    if(file_exists(_PS_THEME_CACHE_DIR_.'module_controller_index.php'))
      $this->overrided_module_controller = include _PS_THEME_CACHE_DIR_.'module_controller_index.php';
    
    if(!is_array($this->overrided_module_controller))
      $this->overrided_module_controller = array();
  }

  /**
   * Load the right classes
   * @param string $moduleName
   * @param string $moduleControllerName
   * @static 
   */
  public static function load($moduleName, $moduleControllerName)
  {
    $self = new self($moduleName, $moduleControllerName);
    $self->_load();
  }

  /**
   * Load all classes for this module
   */
  protected function _load()
  {
    // If not override, then we load basic file
    if(!file_exists(_PS_THEME_DIR_.'modules/'.$this->module_name.'/controllers/front/'.$this->module_controller_name.'.php')) {
      include_once _PS_MODULE_DIR_.$this->module_controller_name.'/'.$this->module_controller_name.'.php';
      include_once(_PS_MODULE_DIR_.$this->module_name.'/controllers/front/'.$this->module_controller_name.'.php');
    }
    else
    {
      // else we load the parent class
      $this->loadOverridedModuleController();
      
      // and the child class
      require_once _PS_THEME_DIR_.'modules/'.$this->module_name.'/controllers/front/'.$this->module_controller_name.'.php';
    }
  }


  /**
   * Load and generate the parent classe
   */
  protected function loadOverridedModuleController()
  {
    if(!file_exists($this->module_controller_core_path) || $this->hasChanged()) {
      $this->generateCodeModuleControllerFile();
    }
    require_once $this->module_controller_core_path;
  }

  /**
   * Generate the parent class (with change name)
   * and update the filemtime file
   */
  protected function generateCodeModuleControllerFile()
  {
    // Rewrite the name class
    $moduleControllerCore = preg_replace('/class\s+([a-zA-Z0-9_-]+)/', 'class $1ModuleController', file_get_contents(_PS_MODULE_DIR_.$this->module_name.'/controllers/front/'.$this->module_controller_name.'.php'));
    // Rewrite the dirname rules
    $moduleControllerCore = preg_replace('/dirname\(__FILE__\)/i', '\''._PS_MODULE_DIR_.$this->module_controller_name.'\'', $moduleControllerCore);
    // Replace the private methods by protected (for allowed rewrite in extended classes)
    $moduleControllerCore = str_ireplace('private', 'protected', $moduleControllerCore);
    
    file_put_contents($this->module_controller_core_path, $moduleControllerCore, LOCK_EX);
    $this->overrided_module_controller[$this->module_name."_".$this->module_controller_name] = filemtime(_PS_MODULE_DIR_.$this->module_name.'/controllers/front/'.$this->module_controller_name.'.php');
    $this->generateIndex();
  }

  /**
   * Return true if the file of parent class was a change
   * @return bool
   */
  protected function hasChanged()
  {
    return !array_key_exists($this->module_name."_".$this->module_controller_name, $this->overrided_module_controller) || $this->overrided_module_controller[$this->module_name."_".$this->module_controller_name] != filemtime(_PS_MODULE_DIR_.$this->module_name.'/controllers/front/'.$this->module_controller_name.'.php');
  }

  /**
   * Generate file width array of filetime
   */
  protected function generateIndex()
  {
    $content = '<?php return '.var_export($this->overrided_module_controller, true).'; ?>';

    // Write classes index on disc to cache it
    $filename = _PS_THEME_CACHE_DIR_.'module_controller_index.php';
    if ((file_exists($filename) && !is_writable($filename)) || !is_writable(dirname($filename)))
    {
      header('HTTP/1.1 503 temporarily overloaded');
      // Cannot use PrestaShopException in this context
      die($filename.' is not writable, please give write permissions (chmod 666) on this file.');
    }
    else
    {
      // Let's write index content in cache file
      // In order to be sure that this file is correctly written, a check is done on the file content
      $loop_protection = 0;
      do
      {
        $integrity_is_ok = false;
        file_put_contents($filename, $content, LOCK_EX);
        if ($loop_protection++ > 10)
          break;

        // If the file content end with PHP tag, integrity of the file is ok
        if (preg_match('#\?>\s*$#', file_get_contents($filename)))
          $integrity_is_ok = true;
      }
      while (!$integrity_is_ok);

      if (!$integrity_is_ok)
      {
        file_put_contents($filename, '<?php return array(); ?>', LOCK_EX);
        // Cannot use PrestaShopException in this context
        die('Your file '.$filename.' is corrupted. Please remove this file, a new one will be regenerated automatically');
      }
    }
  }
}