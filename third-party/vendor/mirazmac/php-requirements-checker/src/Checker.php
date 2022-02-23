<?php

namespace WSAL_Vendor\MirazMac\Requirements;

/**
* PHP Requirements Checker
*
* A quick library to check the current environment against a set of defined requirements.
* Currently it supports checking for PHP version, OS, Extensions, PHP INI values,
* Functions, Classes, Apache Modules and local Files and Folders.
*
* @author Miraz Mac <mirazmac@gmail.com>
* @link https://mirazmac.com Author Homepage
* @version 0.1
* @license LICENSE The MIT License
* @package MirazMac\Requirements
*/
class Checker
{
    /**
     * Identifier for is_file() check
     */
    const CHECK_IS_FILE = 'is_file';
    /**
     * Identifier for is_dir() check
     */
    const CHECK_IS_DIR = 'is_dir';
    /**
     * Identifier for is_readable() check
     */
    const CHECK_IS_READABLE = 'is_readable';
    /**
     * Identifier for is_writable() check
     */
    const CHECK_IS_WRITABLE = 'is_writable';
    /**
     * Identifier for file_exists() check
     */
    const CHECK_FILE_EXISTS = 'file_exists';
    /**
     * Identifier for Unix
     */
    const OS_UNIX = 'UNIX';
    /**
     * Identifier for Windows
     */
    const OS_DOS = 'DOS';
    /**
     * Requirements as an array
     *
     * @var array
     */
    protected $requirements;
    /**
     * Parsed state of the requirements
     *
     * @var array
     */
    protected $parsedRequirements;
    /**
     * Stores if current requirements criteria is satisfied or not
     *
     * @var boolean
     */
    protected $satisfied = \true;
    /**
     * Basic locales for comparison operators
     *
     * @var array
     */
    protected $locale = ['>' => 'greater than', '=' => 'equal to', '<' => 'lower than', '>=' => 'greater than or equal to', '=<' => 'lower than or equal to'];
    /**
     * Array of the total errors
     *
     * @var array
     */
    protected $errors = [];
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->resetRequirements();
    }
    /**
     * Perform checks on the passed requirements
     *
     * @return array The parsed requirements
     */
    public function check()
    {
        $this->parsedRequirements['system'] = $this->validateSystemRequirement();
        $this->parsedRequirements['extensions'] = $this->validateExtensionRequirement();
        $this->parsedRequirements['apache_modules'] = $this->validateApacheModuleRequirement();
        $this->parsedRequirements['functions'] = $this->validateFunctionRequirement();
        $this->parsedRequirements['classes'] = $this->validateClassRequirement();
        $this->parsedRequirements['ini_values'] = $this->validateIniRequirement();
        $this->parsedRequirements['files'] = $this->validateFileRequirement();
        return $this->parsedRequirements;
    }
    /**
     * Resets the requirements to default
     *
     * @return Checker
     */
    public function resetRequirements()
    {
        $default = ['system' => ['php_version' => null, 'os' => null], 'ini_values' => [], 'files' => [], 'extensions' => [], 'classes' => [], 'functions' => [], 'apache_modules' => []];
        $this->requirements = $default;
        $this->parsedRequirements = $default;
        return $this;
    }
    /**
     * Return the requirements added currently
     *
     * @return array
     */
    public function getRequirements()
    {
        return $this->requirements;
    }
    /**
     * Returns if the last requirement check was satisfying or not
     *
     * @return boolean
     */
    public function isSatisfied()
    {
        return $this->satisfied;
    }
    /**
     * Returns all the errors as array
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
    /**
     * Validates only the PHP INI requirements
     *
     * @return array
     */
    public function validateIniRequirement()
    {
        $values = [];
        foreach ($this->requirements['ini_values'] as $key => $value) {
            $setting = \ini_get($key);
            if ($setting == 'On' || $setting == '1') {
                $setting = \true;
            } elseif ($setting == 'Off' || $setting == '' || $setting == '0') {
                $setting = \false;
            }
            $data = $this->getParsedStructure();
            $data['preferred'] = $value;
            $data['current'] = $setting;
            // So you only want to show the value?
            // No validation? Fine.
            if (\is_null($value)) {
                $data['satisfied'] = \true;
                $values[$key] = $data;
                continue;
            }
            if (\is_bool($value)) {
                $data['preferred'] = $value ? 'On' : 'Off';
                $data['current'] = $setting ? 'On' : 'Off';
                if ($value) {
                    $data['satisfied'] = $setting;
                } else {
                    $data['satisfied'] = !$setting;
                }
                if (!$data['satisfied']) {
                    $this->satisfied = \false;
                    $data['message'] = "The php.ini setting `{$key}` should be {$data['preferred']}, currently it's set to {$data['current']}";
                    $this->errors[] = $data['message'];
                }
                $values[$key] = $data;
                continue;
            }
            $parsed = $this->parseComparisonString($value, '=');
            $value = $parsed['plain'];
            $operator = $parsed['operator'];
            $newcfg = $this->returnBytes($setting);
            $newval = $this->returnBytes($value);
            // Acknowledge '-1'(unlimited) values
            // @see https://github.com/MirazMac/php-requirements-checker/issues/2
            $data['satisfied'] = $setting == '-1' ? \true : $this->looseComparison($newcfg, $operator, $newval);
            if (!$data['satisfied']) {
                $this->satisfied = \false;
                $data['message'] = "The php.ini setting `{$key}` should be {$this->locale[$operator]} {$value}. Currently it's set to {$setting}";
                $this->errors[] = $data['message'];
            }
            $values[$key] = $data;
        }
        return $values;
    }
    /**
     * Validates only the classes requirement
     *
     * @return array
     */
    public function validateClassRequirement()
    {
        $values = [];
        foreach ($this->requirements['classes'] as $key => $class) {
            $data = $this->getParsedStructure();
            $data['preferred'] = $class;
            $data['current'] = \false;
            $satisfied = \false;
            foreach (\explode('|', $class) as $className) {
                if (\class_exists($this->ensureNamespace($className))) {
                    $satisfied = \true;
                    break;
                }
            }
            if ($satisfied) {
                $data['satisfied'] = \true;
                $data['current'] = \true;
            } else {
                $this->satisfied = \false;
                $data['message'] = "Class `{$class}` is not defined";
                $this->errors[] = $data['message'];
            }
            $values[$class] = $data;
        }
        return $values;
    }
    /**
     * Validates apache module requirements
     *
     * @return     array
     */
    public function validateApacheModuleRequirement()
    {
        $values = [];
        // Run Only in apache servers
        if (!\function_exists('apache_get_modules')) {
            return $values;
        }
        $modules = \apache_get_modules();
        foreach ($this->requirements['apache_modules'] as $key => $module) {
            $structure = $this->getParsedStructure();
            $structure['preferred'] = $module;
            $structure['current'] = \true;
            $structure['satisfied'] = \true;
            $satisfied = \false;
            foreach (\explode('|', $module) as $moduleName) {
                if (\in_array($moduleName, $modules)) {
                    $satisfied = \true;
                    break;
                }
            }
            if (!$satisfied) {
                $structure['satisfied'] = \false;
                $structure['current'] = \false;
                $this->satisfied = \false;
                $structure['message'] = "Apache module `{$module}` is not loaded";
                $this->errors[] = $structure['message'];
            }
            $values[$module] = $structure;
        }
        return $values;
    }
    /**
     * Validates only the functions requirement
     *
     * @return array
     */
    public function validateFunctionRequirement()
    {
        $values = [];
        foreach ($this->requirements['functions'] as $key => $func) {
            $data = $this->getParsedStructure();
            $data['preferred'] = $func;
            $data['current'] = \false;
            $satisfied = \false;
            foreach (\explode('|', $func) as $function) {
                if (\function_exists($function)) {
                    $satisfied = \true;
                    break;
                }
            }
            if ($satisfied) {
                $data['satisfied'] = \true;
                $data['current'] = \true;
            } else {
                $this->satisfied = \false;
                $data['message'] = "PHP function `{$func}()` is not defined";
                $this->errors[] = $data['message'];
            }
            $values[$func] = $data;
        }
        return $values;
    }
    /**
     * Validates only the extensions requirement
     *
     * @return array
     */
    public function validateExtensionRequirement()
    {
        $values = [];
        foreach ($this->requirements['extensions'] as $key => $ext) {
            $data = $this->getParsedStructure();
            $data['preferred'] = $ext;
            $data['current'] = \false;
            $satisfied = \false;
            foreach (\explode('|', $ext) as $extension) {
                if (\extension_loaded($extension)) {
                    $satisfied = \true;
                    break;
                }
            }
            if ($satisfied) {
                $data['satisfied'] = \true;
                $data['current'] = \true;
            } else {
                $this->satisfied = \false;
                $data['message'] = "PHP extension {$ext} is not loaded";
                $this->errors[] = $data['message'];
            }
            $values[$ext] = $data;
        }
        return $values;
    }
    /**
     * Validate only the system requirements, includes the php version, OS and apache version
     *
     * @return array
     */
    public function validateSystemRequirement()
    {
        $values = [];
        if ($this->requirements['system']['php_version']) {
            $structure = $this->getParsedStructure();
            $structure['current'] = \PHP_VERSION;
            $structure['preferred'] = $this->requirements['system']['php_version'];
            $parsed = $this->parseComparisonString($this->requirements['system']['php_version'], '>=');
            $result = \version_compare(\PHP_VERSION, $parsed['plain'], $parsed['operator']);
            $structure['satisfied'] = $result;
            if (!$result) {
                $this->satisfied = \false;
                $structure['message'] = \sprintf('PHP version must be %1$s %2$s', $this->locale[$parsed['operator']], $structure['preferred']);
                $this->errors[] = $structure['message'];
            }
            $values['php_version'] = $structure;
        }
        // Now the OS
        if ($this->requirements['system']['os']) {
            $structureOS = $this->getParsedStructure();
            $os = \DIRECTORY_SEPARATOR === '\\' ? static::OS_DOS : static::OS_UNIX;
            $structureOS['satisfied'] = \true;
            $structureOS['preferred'] = $this->requirements['system']['os'];
            $structureOS['current'] = $os;
            if ($os !== $structureOS['preferred']) {
                $structureOS['satisfied'] = \false;
                $this->satisfied = \false;
                $structureOS['message'] = "The operating system must be {$structureOS['preferred']}, currently we are on a {$os} system";
                $this->errors[] = $structureOS['message'];
            }
            $values['os'] = $structureOS;
        }
        return $values;
    }
    /**
     * Validates only the file requirements
     *
     * @return array
     */
    public function validateFileRequirement()
    {
        $values = [];
        foreach ($this->requirements['files'] as $file => $checks) {
            $structure = $this->getParsedStructure();
            $file = $this->unixPath($file);
            $structure['path'] = $file;
            $type = 'path';
            $exists = \file_exists($file);
            if (\is_file($file)) {
                $type = 'file';
            } elseif (\is_dir($file)) {
                $type = 'directory';
            }
            foreach ($checks as $check) {
                $data = $structure;
                $data['preferred'] = $check;
                $data['satisfied'] = (bool) $check($file);
                $data['current'] = $data['satisfied'];
                if (!$data['satisfied']) {
                    $this->satisfied = \false;
                    switch ($check) {
                        case static::CHECK_IS_DIR:
                            $data['message'] = "The path `{$file}` must be a directory";
                            if (!$exists) {
                                $data['message'] .= ", but the path doesn't even exist";
                            } elseif ($type === 'file') {
                                $data['message'] .= ', but the path is a file';
                            }
                            $data['current'] = "No directory";
                            break;
                        case static::CHECK_IS_FILE:
                            $data['message'] = "The path `{$file}` must be a file";
                            $data['current'] = "No file";
                            if (!$exists) {
                                $data['message'] .= ", but the path doesn't even exist";
                            } elseif ($type === 'directory') {
                                $data['message'] .= ', but the path is a directory';
                            }
                            break;
                        case static::CHECK_FILE_EXISTS:
                            $data['message'] = "The path `{$file}` doesn't exist";
                            $data['current'] = "Doesn't exist";
                            break;
                        case static::CHECK_IS_READABLE:
                        case static::CHECK_IS_WRITABLE:
                            $humanFriendly = \str_replace('is_', '', $check);
                            $data['current'] = "Not {$humanFriendly}";
                            $data['message'] = "The path `{$file}` must be {$humanFriendly}";
                            if (!$exists) {
                                $data['message'] .= ", but the path doesn't even exist";
                            }
                            break;
                    }
                    $this->errors[] = $data['message'];
                }
                $values[] = $data;
            }
        }
        return $values;
    }
    /**
     * Require a certain OS
     *
     * @param  string $os The OS would pass the test
     *                      Possible values: DOS, WIN
     * @return Checker
     */
    public function requireOS($os)
    {
        $this->requirements['system']['os'] = $os;
        return $this;
    }
    /**
     * Require php.ini config values
     *
     * @param  array  $values As key => value format
     * @return Checker
     */
    public function requireIniValues(array $values)
    {
        foreach ($values as $key => $value) {
            $this->requirements['ini_values'][$key] = $value;
        }
        return $this;
    }
    /**
     * Set required PHP version
     *
     * @param  string $version The required PHP version
     * @return Checker
     */
    public function requirePhpVersion($version)
    {
        $this->requirements['system']['php_version'] = $version;
        return $this;
    }
    /**
     * Add required extensions
     *
     * @param  array|string $extensions The exact name(s) of the extension as they appear of the phpinfo() page
     * @return Checker
     */
    public function requirePhpExtensions(array $extensions)
    {
        foreach ($extensions as $ext) {
            $this->requirements['extensions'][$ext] = $ext;
        }
        return $this;
    }
    /**
     * Add required functions(s)
     *
     * @param  array $functions Required function(s) name
     * @return Checker
     */
    public function requireFunctions(array $functions)
    {
        foreach ($functions as $func) {
            $this->requirements['functions'][$func] = $func;
        }
        return $this;
    }
    /**
     * Add required classe(es)
     *
     * @param  array $classes Required class(es) name
     * @return Checker
     */
    public function requireClasses(array $classes)
    {
        foreach ($classes as $class) {
            $this->requirements['classes'][$class] = $class;
        }
        return $this;
    }
    /**
     * Require list of apache modules (check will be performed only if server is apache)
     *
     * @param  array  $modules
     * @return Checker
     */
    public function requireApacheModules(array $modules)
    {
        foreach ($modules as $module) {
            $this->requirements['apache_modules'][$module] = $module;
        }
        return $this;
    }
    /**
     * Require a file or folder with appropriate check
     *
     * @param  string $path Path to the file/directory
     * @param  string $check Any of the supported checks, defaults to file_exists
     * @return Checker
     */
    public function requireFile($path, $check = self::CHECK_FILE_EXISTS)
    {
        $supportedChecks = ['is_file', 'is_dir', 'is_readable', 'is_writable', 'file_exists'];
        if (!\in_array($check, $supportedChecks)) {
            throw new \InvalidArgumentException("No such check is supported!");
        }
        $this->requirements['files'][$path][] = $check;
        return $this;
    }
    /**
     * Alias of $this->requireFile() for people who are extra concerned about verbosity
     *
     * @param  string $path Path to the file/directory
     * @param  string $check Any of the supported checks, defaults to file_exists
     * @return Checker
     */
    public function requireDirectory($path, $check = self::CHECK_FILE_EXISTS)
    {
        return $this->requireFile($path, $check);
    }
    /**
     * Remove extensions requirements
     *
     * @param  array  $keys extensions to remove as an array
     * @return Checker
     */
    public function removeExtensionsRequirement(array $keys)
    {
        foreach ($keys as $key) {
            unset($this->requirements['extensions'][$key]);
        }
        return $this;
    }
    /**
     * Remove apache modules requirements
     *
     * @param  array  $keys apache modules to remove as an array
     * @return Checker
     */
    public function removeApacheModulesRequirement(array $keys)
    {
        foreach ($keys as $key) {
            unset($this->requirements['apache_modules'][$key]);
        }
        return $this;
    }
    /**
     * Remove classes requirements
     *
     * @param  array  $keys classes to remove as an array
     * @return Checker
     */
    public function removeClassesRequirement(array $keys)
    {
        foreach ($keys as $key) {
            unset($this->requirements['classes'][$key]);
        }
        return $this;
    }
    /**
     * Remove functions requirements
     *
     * @param  array  $keys functions to remove as an array
     * @return Checker
     */
    public function removeFunctionsRequirement(array $keys)
    {
        foreach ($keys as $key) {
            unset($this->requirements['functions'][$key]);
        }
        return $this;
    }
    /**
     * Remove INI requirements
     *
     * @param  array  $keys Keys as an array
     * @return Checker
     */
    public function removeIniValuesRequirement(array $keys)
    {
        foreach ($keys as $key) {
            unset($this->requirements['ini_values'][$key]);
        }
        return $this;
    }
    /**
     * Remove a file/directory requirement
     *
     * @param string $path The file path that you added earlier using self::requireFile()
     * @param string|null The check name to remove, set this to null/empty and
     *                    the entire path will be removed with all the checks.
     * @return Checker
     */
    public function removeFilesRequirement($path, $check = null)
    {
        // Make sure the path is added
        if (!isset($this->requirements['files'][$path])) {
            return $this;
        }
        // No check passed so just remove the file entirely
        if (!$check) {
            unset($this->requirements['files'][$path]);
            return $this;
        }
        $newVals = $this->arrayRemoveValue($this->requirements['files'][$path], [$check]);
        $this->requirements['files'][$path] = $newVals;
        return $this;
    }
    /**
     * Remove currently set OS requirements
     *
     * @return Checker
     */
    public function removeOsRequirement()
    {
        $this->requirements['system']['os'] = null;
        return $this;
    }
    /**
     * Remove PHP version requirement
     *
     * @return Checker
     */
    public function removePhpVersionRequirement()
    {
        $this->requirements['system']['php_version'] = null;
        return $this;
    }
    /**
     * Remove specific value from one-dimensional array
     *
     * @param array $array The array to remove values from
     * @param array $removals List of values to remove
     *
     * @return array
     */
    public function arrayRemoveValue(array $array, array $removals)
    {
        return \array_diff($array, $removals);
    }
    /**
     * Transform any file path to unix style path
     *
     * @param  string $string
     * @return string
     */
    public function unixPath($string)
    {
        return \str_replace('\\', '/', $string);
    }
    /**
     * Checks if a string is PHP style namespaced using backslash or not
     *
     * @param  string  $string
     * @return boolean
     */
    public function ensureNamespace($string)
    {
        // Already namespaced
        if (\strpos($string, '\\') !== \false) {
            return $string;
        }
        // Append a global namespace
        return '\\' . $string;
    }
    /**
     * Parse a string that has comparison operator prepended to it
     *
     * @param  string $string
     * @param  string $default
     * @return array  As operator => the comparison operator if exists
     *                    plain => the string without the operator
     **/
    public function parseComparisonString($string, $default)
    {
        $comparison = \substr($string, 0, 1);
        $comparison2 = \substr($string, 0, 2);
        $operator = $default;
        $withoutVersion = $string;
        if ($comparison2 == '>=' || $comparison2 == '<=') {
            $operator = $comparison2;
            $withoutVersion = \substr($string, 2);
        } elseif ($comparison == '>' || $comparison == '<' || $comparison == '=') {
            $operator = $comparison;
            $withoutVersion = \substr($string, 1);
        }
        return ['operator' => $operator, 'plain' => $withoutVersion];
    }
    /**
     * Loosely compare two variables with the comparison operator provided
     *
     * @param  mixed $var1
     * @param  string $op
     * @param  mixed $var2
     * @return boolean
     */
    public function looseComparison($var1, $op, $var2)
    {
        switch ($op) {
            case "=":
                return $var1 == $var2;
            case "!=":
                return $var1 != $var2;
            case ">=":
                return $var1 >= $var2;
            case "<=":
                return $var1 <= $var2;
            case ">":
                return $var1 > $var2;
            case "<":
                return $var1 < $var2;
            default:
                return \true;
        }
    }
    /**
     * Returns bytes from php.ini string values
     *
     * @param  string $val
     * @return integer
     */
    public function returnBytes($val)
    {
        $val = \strtolower(\trim($val));
        if (\substr($val, -1) == 'b') {
            $val = \substr($val, 0, -1);
        }
        $last = \substr($val, -1);
        $val = \intval($val);
        switch ($last) {
            case 'g':
            case 'gb':
                $val *= 1024;
            // no break
            case 'm':
            case 'mb':
                $val *= 1024;
            // no break
            case 'k':
            case 'kb':
                $val *= 1024;
        }
        return $val;
    }
    /**
     * Returns base structure for the values
     *
     * @return array
     */
    protected function getParsedStructure()
    {
        return ['satisfied' => \false, 'preferred' => null, 'current' => null, 'message' => null];
    }
}
