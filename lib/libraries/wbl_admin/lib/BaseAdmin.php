<?php
/**
 * Model class for back-end
 */

/**
 * Model Class extender for back-end section
 * @package WebLauncher\Objects
 */
class Base extends _Base {
    /**
     * @var array $admin_fields Admin fields configuration data
     */
    var $admin_fields = array();
    /**
     * @var array $admin_actions Admin actions configuration
     */
    var $admin_actions = array();
    /**
     * @var array $admin_form_options Admin form options
     */
    var $admin_form_options = array();
    /**
     * @var array $admin_table_options Admin table options
     */
    var $admin_table_options = array();
    /**
     * @var string $admin_init_function Admin init function
     */
    var $admin_init_function = 'AdminInit';
    /**
     * @var string $admin_table_filter Condition to filter the table
     */
    var $admin_table_filter='';

    /**
     * Admin init function
     */
    private function _init_admin() {
        if (method_exists($this, $this -> admin_init_function)) {
            $func = $this -> admin_init_function;
            $this -> $func();
        } else {
            $columns = $this -> columns();
            if (count($columns)) {
                $this -> admin_fields = array();
                foreach($columns as $v)
                    $this->admin_fields[$v['Field']]=array('table','form');
                $this -> add_action('Edit', '', $this -> system -> paths['current'] . '?a=edit:{$o.id.value}', '', 0, 'fa-pencil', '');
                $this -> add_action("Delete", "", "", 'delete:{$o.id.value}', 1, "fa-trash-o", "Are you sure you want to delete this?");
            } else {
                $trace = debug_backtrace();
                System::triggerError('Undefined function ' . get_class($this) . '->' . $this -> admin_init_function . ':' . ' in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_NOTICE);
                return null;
            }
        }
    }

    /**
     * Get admin ajax table
     * @param string $id
     * @param int $data
     * @param string $cond
     * @param string $update_action
     * @param string $edit_link
     * @param int $sort_col_no
     * @return string
     */
    function get_admin_table($id = 'ajax_table', $data = 0, $cond = '', $update_action = 'update', $edit_link = 'none', $sort_col_no = 0) {
        

        $table = new AjaxTable();
        $this -> _init_admin();
        $table -> id = $id;
        if ($data) {
            foreach ($this->admin_fields as $k => $v) {
                $name = isset($v['table']['name']) ? $v['table']['name'] : ucwords(str_replace('_', ' ', $k));
                if (isset($v['table'])) {
                    $v['table']['name'] = $name;
                    $table -> header[] = array_merge(array('col' => $k), $v['table']);
                } elseif (in_array('table', $v))
                    $table -> header[] = array_merge(array('col' => $k), array('name' => $name));
            }
            $table -> process_request();
            
            if($this->admin_table_filter)
                $cond.=' 1=1 AND ('.$this->admin_table_filter.')';
            
            $table -> process_content($this -> get_all(System::getInstance() -> page_skip, System::getInstance() -> page_offset, $table -> sort_by, $table -> sort_dir, $cond, true, $table -> get_search_fields(), $table -> search_keyword));
            System::getInstance() -> no_total_rows = $this -> total_rows;

            foreach ($this->admin_actions as $v)
                $table -> add_action(isset($v['title']) ? $v['title'] : '', isset($v['text']) ? $v['text'] : '', isset($v['link']) ? $v['link'] : '', isset($v['onclick']) ? $v['onclick'] : '', isset($v['refresh']) ? $v['refresh'] : 1, isset($v['icon']) ? $v['icon'] : '', isset($v['confirm']) ? $v['confirm'] : '');
        }
        $table -> update_action = $update_action;
        $table -> edit_link = $edit_link;
        $table -> sort_col_no = $sort_col_no;
        $table -> total = System::getInstance() -> no_total_rows;
        foreach ($this->admin_table_options as $k => $v)
            $table -> {$k} = $v;

        if ($data)
            $table -> display_data();
        else
            return $table -> get_array();
    }

    /**
     * Add action to ajax table
     * @param string $title
     * @param string $text
     * @param string $link
     * @param string $onclick
     * @param bool $refresh
     * @param string $icon
     * @param string $confirm
     */
    function add_action($title = '', $text = '', $link = '', $onclick = '', $refresh = true, $icon = '', $confirm = '') {
        $this -> admin_actions[] = array(
            'title' => $title,
            'text' => $text,
            'link' => $link,
            'onclick' => $onclick,
            'refresh' => $refresh,
            'icon' => $icon,
            'confirm' => $confirm
        );
    }

    /**
     * Get admin generated form data
     * @param string $title
     * @param string $description
     * @param string $action_url
     * @param string $action
     * @param string $id
     * @param array $values
     * @return string
     */
    function get_admin_form($title = '', $description = '', $action_url = '', $action = '', $id = '', $values = array()) {
        $row = '';
        if ($id)
            $row = $this -> get($id);
        $this -> _init_admin();

        $name = get_class($this) . '_' . str_replace(':', '_', $action);
        $form = new Wbl_Form_Generator($name, $name);
        
        $form -> link_btn_cancel = isset_or($this -> admin_form_options['link_btn_cancel'], System::getInstance() -> paths['current']);
        $form -> startZone($title, $description);
        $form -> addHidden('Action', 'a', $action);

        $form = $this -> get_admin_form_layout($form, $row ? $row : $values);
        return $form -> getForm();
    }

    /**
     * Get admin form layout
     * @param Form $form
     * @param mixed $row
     * @return Form
     */
    function get_admin_form_layout($form, $row = '') {
        foreach ($this->admin_fields as $k => $v)
            if (isset($v['form']) || in_array('form', $v)) {
                $attribute = isset_or($v['form'], array());
                $attribute['name'] = $k;
                if (isset($row[$k]))
                    $attribute['value'] = $row[$k];
                if (!isset($v['form']['type']))
                    $v['form']['type'] = 'text';
                if (!isset($v['form']['label']))
                    $v['form']['label'] = ucwords(str_replace('_', ' ', $k));
                $form -> addField(isset_or($v['form']['label']), $v['form']['type'], $attribute);
            }
        return $form;
    }

    /**
     * Insert data posted by admin form
     * @return int
     */
    function insert_from_admin_form() {
        $this -> _init_admin();
        $params = array();
        foreach ($this->admin_fields as $k => $v)
            if ((isset($v['form']) || in_array('form', $v)) && isset_or($v['form']['insert'], true) && isset($_POST[$k])) {
                $params[$k] = $_POST[$k];
            }
        return $this -> insert($params);
    }

    /**
     * Update data posted by admin form
     * @param string $id
     * @return int
     */
    function update_from_admin_form($id) {
        $this -> _init_admin();
        $params = array();
        $params[$this -> id_field] = $id;
        foreach ($this->admin_fields as $k => $v)
            if ((isset($v['form']) || in_array('form', $v)) && isset_or($v['form']['insert'], true)) {
                $params[$k] = $_POST[$k];
            }
        $params[$this -> id_field] = $id;
        return $this -> update($params);
    }

    /**
     * Export data from table model
     * @param string $type
     * @param string $file_name
     * @param string $admin_init_function
     * @return mixed
     */
    function export($type = 'none', $file_name = '', $admin_init_function = 'AdminInit') {
        if (method_exists($this, $admin_init_function)) {
            $this -> $admin_init_function();
        } else {
            $trace = debug_backtrace();
            System::triggerError('Undefined function ' . get_class($this) . '->' . $admin_init_function . ':' . ' in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_NOTICE);
            return null;
        }
        
        if (!$file_name)
            $file_name = 'export_' . get_class($this);
        switch($type) {
            case 'none' :
            case 'excel' :
                $file_name .= '.xls';

                $fields = '';
                $header = array();
                foreach ($this->admin_fields as $k => $v)
                    if (!isset($v['export']) || (isset($v['export']) && $v['export'])) {
                        $fields .= ' `' . $this -> table . '`.`' . $k . '`, ';
                        if (isset($v['export']) && $v['export'])
                            $header[] = $v['export'];
                        else
                            $header[] = $k;
                    }
                $fields = substr($fields, 0, strlen($fields) - 2);
                $query = 'select ' . $fields . ' from `' . $this -> table . '`';
                $output = $this -> db -> getAll($query);

                System::getInstance() -> import('class', 'objects.SimpleExcel');
                SimpleExcel::Export($file_name, array_merge(array($header), $output));
                die();
                break;
        }
    }

    /**
     * Import data to admin model
     * @param string $file
     * @param bool $has_header
     * @param string $admin_init_function
     */
    function import($file = 'import', $has_header = 1, $admin_init_function = 'AdminInit') {
        if (method_exists($this, $admin_init_function)) {
            $this -> $admin_init_function();
        } else {
            $trace = debug_backtrace();
            System::triggerError('Undefined function ' . get_class($this) . '->' . $admin_init_function . ':' . ' in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_NOTICE);
            return null;
        }
        
        System::getInstance() -> import('class', 'objects.SimpleExcel');
        $data = SimpleExcel::Import($file, $has_header);

        $errors = array();
        if ($data) {
            $fields = array();
            $pars = array();
            $col = 0;

            foreach ($this->admin_fields as $k => $v) {
                if (is_array($v['import'])) {
                    $fields[] = $k;
                    $nr_rows = count($data);
                    for ($i = 0; $i < $nr_rows; $i++) {
                        if (isset($v['import']['value'])) {
                            $pars[$i][$col] = $v['import']['value'];
                        } else {
                            $value = $data[$i][$v['import']['col']];
                            if (isset($v['import']['date']) && $v['import']['date'])
                                $value = date('Y-m-d', strtotime($value));
                            if (isset($v['import']['filter']))
                                $pars[$i][$col] = $v['import']['filter']($value);
                            else
                                $pars[$i][$col] = $value;
                            if (isset($v['import']['unique']) && $v['import']['unique'] && $this -> exists_cond('`' . $k . '`="' . $value . '"'))
                                if (!in_array($i, $errors)) {
                                    echo 'Line <strong>' . $i . '</strong> not unique <strong>' . $value . '</strong>.<br/>';
                                    flush();
                                    $errors[] = $i;
                                }
                        }
                    }
                    $col++;
                }
            }
            if (count($errors))
                foreach ($errors as $v)
                    array_splice($pars, $v, 1);
            if (count($pars))
                return $this -> insert_multiple($fields, $pars);
            if (count($errors))
                die('Errors where found in the file!');
        }
        return false;
    }

}
?>