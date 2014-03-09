<?php
 
    /**
     * Inspects and prints out PHP values as HTML in a nicer way than print_r().
     * @author Christian Sciberras <christian@sciberras.me>
     * @copyright (c) 2013, Christian Sciberras
     * @license https://raw.github.com/uuf6429/nice_r/master/LICENSE MIT License
     * @link https://github.com/uuf6429/nice_r GitHub Repository
     * @version 2.0
     * @since 2.0
     */
    class WSAL_Nicer {
        protected $value;
         
        /**
         * Allows modification of CSS class prefix.
         * @var string
         */
        public $css_class = 'nice_r';
         
        /**
         * Allows modification of HTML id prefix.
         * @var string
         */
        public $html_id = 'nice_r_v';
         
        /**
         * Allows modification of JS function used to toggle sections.
         * @var string
         */
        public $js_func = 'nice_r_toggle';
         
        /**
         * Since PHP does not support private constants, we'll have to settle for private static fields.
         * @var string
         */
        protected static $BEEN_THERE = '__NICE_R_INFINITE_RECURSION_PROTECT__';
         
        /**
         * Constructs new renderer instance.
         * @param mixed $value The value to inspect and render.
         */
        public function __construct($value){
            $this->value = $value;
        }
         
        /**
         * Generates the inspector HTML and returns it as a string.
         * @return string Generated HTML.
         */
        public function generate(){
            return $this->_generate_value($this->value, $this->css_class);
        }
         
        /**
         * Renders the inspector HTML directly to the browser.
         */
        public function render(){
            echo $this->generate();
        }
 
        /**
         * Converts a string to HTML, encoding any special characters.
         * @param string $text The original string.
         * @return string The string as HTML.
         */
        protected function _esc_html($text){
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
         
        /**
         * Render a single particular value.
         * @param mixed $var The value to render
         * @param string $class Parent CSS class.
         * @param string $id Item HTML id.
         */
        protected function _generate_value($var, $class = '', $id = ''){
            $BEENTHERE = self::$BEEN_THERE;
            $class .= ' '.$this->css_class.'_t_'.gettype($var);
             
            $html = '<div id="'.$id.'" class="'.$class.'">';
             
            switch(true){
 
                // handle arrays
                case is_array($var):
                    if(isset($var[$BEENTHERE])){
                        $html .= '<span class="'.$this->css_class.'_ir">Infinite Recursion Detected!</span>';
                    }else{
                        $var[$BEENTHERE] = true;
                        $has_subitems = false;
                        foreach($var as $k=>$v){
                            if($k!==$BEENTHERE){
                                $html .= $this->_generate_keyvalue($k, $v);
                                $has_subitems = true;
                            }
                        }
                        if(!$has_subitems){
                            $html .= '<span class="'.$this->css_class.'_ni">Empty Array</span>';
                        }
                        unset($var[$BEENTHERE]);
                    }
                    break;
 
                // handle objects
                case is_object($var):
                    if(isset($var->$BEENTHERE)){
                        $html .= '<span class="'.$this->css_class.'_ir">Infinite Recursion Detected!</span>';
                    }else{
                        $var->$BEENTHERE = true;
                        $has_subitems = false;
                        foreach((array)$var as $k=>$v){
                            if($k!==$BEENTHERE){
                                $html .= $this->_generate_keyvalue($k, $v);
                                $has_subitems = true;
                            }
                        }
                        if(!$has_subitems){
                            $html .= '<span class="'.$this->css_class.'_ni">No Properties</span>';
                        }
                        unset($var->$BEENTHERE);
                    }
                    break;
 
                // handle simple types
                default:
                    $html .= $this->_generate_keyvalue('', $var);
                    break;
            }
                 
            return $html . '</div>';
        }
         
        /**
         * Render a key-value pair.
         * @staticvar int $id Specifies element id.
         * @param string $key Key name.
         * @param mixed $val Key value.
         */
        protected function _generate_keyvalue($key, $val){
            static $id = 0; $id++;  // unique (per rquest) id
            $p = '';                // preview
            $d = '';                // description
            $t = gettype($val);     // get data type 
            $is_hash = ($t=='array') || ($t=='object');
             
            switch($t){
                case 'boolean':
                    $p = $val ? 'TRUE' : 'FALSE';
                    break;
                case 'integer':
                case 'double':
                    $p = (string)$val;
                    break;
                case 'string':
                    $d .= ', '.strlen($val).' characters';
                    $p = $val;
                    break;
                case 'resource':
                    $d .= ', '.get_resource_type($val).' type';
                    $p = (string)$val;
                    break;
                case 'array':
                    $d .= ', '.count($val).' elements';
                    break;
                case 'object':
                    $d .= ', '.get_class($val).', '.count(get_object_vars($val)).' properties';
                    break;
            }
             
            $cls = $this->css_class;
            $xcls = !$is_hash ? $cls.'_ad' : '';
            $html  = '<a href="javascript:;" onclick="'.$this->js_func.'(\''.$this->html_id.'\',\''.$id.'\');">';
            $html .= '  <span class="'.$cls.'_a '.$xcls.'" id="'.$this->html_id.'_a'.$id.'">&#9658;</span>';
            $html .= '  <span class="'.$cls.'_k">'.$this->_esc_html($key).'</span>';
            $html .= '  <span class="'.$cls.'_d">(<span>'.ucwords($t).'</span>'.$d.')</span>';
            $html .= '  <span class="'.$cls.'_p '.$cls.'_t_'.$t.'">'.$this->_esc_html($p).'</span>';
            $html .= '</a>';
             
            if($is_hash){
                $html .= $this->_generate_value($val, $cls.'_v', $this->html_id.'_v'.$id);
            }
             
            return $html;
        }
    }