<?php

class application extends module
{

    var $action;
    var $member_id;

    function __construct()
    {
        $this->name = "application";
        $this->app_action = '';
        $this->popup = false;
    }

    function saveParams($data = 1)
    {
        $p = array();
        if (isset($this->action) && $this->action) {
            $p["action"] = $this->action;
        }
        if (isset($this->ajax) && $this->ajax) {
            $p['ajax'] = $this->ajax;
        }
        if (isset($this->popup) && $this->popup) {
            $p['popup'] = $this->popup;
        }
        if (isset($this->app_action) && $this->app_action) {
            $p['app_action'] = $this->app_action;
        }
        return parent::saveParams($p);
    }

// --------------------------------------------------------------------
    function getParams()
    {
        $action = gr('action');
        if ($action != '') $this->action = $action;
    }

// --------------------------------------------------------------------
    function run()
    {
        global $session;

        Define('ALTERNATIVE_TEMPLATES', 'templates_alt');

        $out = array();

        $theme = SETTINGS_THEME;
        if ($this->action == 'layouts' && $this->id) {
            $layout_rec = SQLSelectOne("SELECT * FROM layouts WHERE ID=" . (int)$this->id);
            if ($layout_rec['THEME']) {
                $theme = $layout_rec['THEME'];
            }
            if ($layout_rec['BACKGROUND_IMAGE']) {
                $out['BODY_CSS'] .= 'background-image:url(' . $layout_rec['BACKGROUND_IMAGE'] . ')';
            }
        }
        Define('THEME', $theme);

        if ($this->action == 'ajaxgetglobal') {
            header("HTTP/1.0: 200 OK\n");
            header('Content-Type: text/html; charset=utf-8');
            $var = gr('var');
            $var = str_replace('%', '', $var);
            $res['DATA'] = getGlobal($var);
            echo json_encode($res);
            exit;
        }

        if ($this->action == 'ajaxsetglobal') {
            header("HTTP/1.0: 200 OK\n");
            header('Content-Type: text/html; charset=utf-8');
            $var = gr('var');
            $var = str_replace('%', '', $var);
            setGlobal($var, gr('value'));
            $res['DATA'] = 'OK';
            echo json_encode($res);
            exit;
        }

        if ($this->action == 'getlatestnote') {
            header("HTTP/1.0: 200 OK\n");
            header('Content-Type: text/html; charset=utf-8');

            $msg = SQLSelectOne("SELECT * FROM shouts WHERE MEMBER_ID=0 ORDER BY ID DESC LIMIT 1");
            $res = array();
            $res['DATA'] = $msg['MESSAGE'];
            echo json_encode($res);
            exit;
        }

        if ($this->action == 'getlatestmp3') {
            header("HTTP/1.0: 200 OK\n");
            header('Content-Type: text/html; charset=utf-8');
            if ($dir = @opendir(ROOT . "cms/cached/voice")) {
                while (($file = readdir($dir)) !== false) {
                    if (preg_match('/\.mp3$/', $file)) {
                        $mtime = filemtime(ROOT . "cms/cached/voice/" . $file);
                        $files[] = array('FILENAME' => $file, 'MTIME' => $mtime);
                    }
                    if (preg_match('/\.wav$/', $file)) {
                        $mtime = filemtime(ROOT . "cms/cached/voice/" . $file);
                    }

                }
                closedir($dir);
            }

            if (is_array($files)) {
                function sortFiles($a, $b)
                {
                    if ($a['MTIME'] == $b['MTIME']) return 0;
                    return ($a['MTIME'] > $b['MTIME']) ? -1 : 1;
                }

                usort($files, 'sortFiles');
                echo '/cms/cached/voice/' . $files[0]['FILENAME'];
            }

            exit;
        }

        if (!$this->action && (!defined('SETTINGS_SITE_LANGUAGE') || !defined('SETTINGS_SITE_TIMEZONE') || !defined('SETTINGS_HOOK_BEFORE_SAY'))) {
            $this->action = 'first_start';
        }

        if ($this->action == 'first_start') {
            include(DIR_MODULES . 'first_start.php');
        } elseif
        ($this->action == 'apps') {
            include(DIR_MODULES . 'apps.php');
        }

        $out["ACTION"] = $this->action;
        $out["TODAY"] = date('l, F d, Y');

        $terminal = gr('terminal');
        if ($terminal) {
            $session->data['TERMINAL'] = $terminal;
        }

        if ($this->action != 'apps') {
            if (isset($this->action) && $this->action &&
                (preg_match('/^app_\w+$/is', $this->action) || $this->action == 'xray')) {
                $out['APP_ACTION'] = 1;
            }

            if (isset($this->app_action) && $this->app_action && $this->action != '') {
                $out['APP_ACTION'] = 1;
            }
        }

        if (isset($this->app_action) && $this->app_action == 'panel') {
            $this->redirect(ROOTHTML . 'admin.php');
        }

        if (file_exists(DIR_MODULES . 'app_player')) {
            $out['SHOW_PLAYER'] = 1;
        }

        $terminals = getAllTerminals(-1, 'TITLE');
        $total = count($terminals);
        for ($i = 0; $i < $total; $i++) {
            if ($terminals[$i]['HOST'] != '' && $_SERVER['REMOTE_ADDR'] == $terminals[$i]['HOST'] && !$session->data['TERMINAL']) {
                $session->data['TERMINAL'] = $terminals[$i]['NAME'];
            }
            if (mb_strtoupper($terminals[$i]['NAME'], 'UTF-8') == mb_strtoupper(isset($session->data['TERMINAL']) ? $session->data['TERMINAL'] : '', 'UTF-8')) {
                $terminals[$i]['LATEST_ACTIVITY'] = date('Y-m-d H:i:s');
                $terminals[$i]['IS_ONLINE'] = 1;
                SQLUpdate('terminals', $terminals[$i]);
                $out['TERMINAL_TITLE'] = $terminals[$i]['TITLE'];
                $terminals[$i]['SELECTED'] = 1;
            }
        }

        $main_terminal = getTerminalsByName('MAIN')[0];
        if (!$main_terminal['ID']) {
            $main_terminal = array();
            $main_terminal['NAME'] = 'MAIN';
            $main_terminal['TITLE'] = 'MAIN';
            $main_terminal['HOST'] = $_SERVER['SERVER_ADDR'];
            SQLInsert('terminals', $main_terminal);
        }

        if (!isset($out['TERMINAL_TITLE']) && isset($session->data['TERMINAL']) && $session->data['TERMINAL']) {
            $new_terminal = array();
            $new_terminal['TITLE'] = $session->data['TERMINAL'];
            $new_terminal['HOST'] = $_SERVER['REMOTE_ADDR'];
            $new_terminal['NAME'] = $new_terminal['TITLE'];
            $new_terminal['LATEST_ACTIVITY'] = date('Y-m-d H:i:s');
            $new_terminal['IS_ONLINE'] = 1;
            SQLInsert('terminals', $new_terminal);
            $out['TERMINAL_TITLE'] = $new_terminal['TITLE'];
            $new_terminal['SELECTED'] = 1;
            $out['TERMINALS'][] = $new_terminal;
        }

        $out['TERMINALS'] = $terminals;
        if ($total == 1) {
            $out['HIDE_TERMINALS'] = 1;
            $session->data['TERMINAL'] = $terminals[0]['NAME'];
        }

        $site_username = isset($session->data['SITE_USERNAME']) ? $session->data['SITE_USERNAME'] : '';

        $username = gr('username');
        if ($username) {
            $user = SQLSelectOne("SELECT * FROM users WHERE USERNAME LIKE '" . DBSafe($username) . "'");
            if (hash('sha512', '') == $user['PASSWORD'] || $user['PASSWORD'] == '') {
                $session->data['SITE_USERNAME'] = $user['USERNAME'];
                $session->data['SITE_USER_ID'] = $user['ID'];
                $site_username = $session->data['SITE_USERNAME'];
            }
        }

        $all_users = SQLSelect("SELECT * FROM users ORDER BY USERNAME");
        if (count($all_users) == 1) {
            $out['HIDE_USERS'] = 1;
        }
        if (!$site_username) {
            $host_user = SQLSelectOne("SELECT * FROM users WHERE HOST!='' AND HOST='" . DBSafe($_SERVER['REMOTE_ADDR']) . "'");
            if (isset($host_user['ID'])) {
                $session->data['SITE_USERNAME'] = $host_user['USERNAME'];
                $session->data['SITE_USER_ID'] = $host_user['ID'];
                $site_username = $session->data['SITE_USERNAME'];
            }
        }
        if (!$site_username) {
            $default_user = SQLSelectOne("SELECT * FROM users WHERE IS_DEFAULT=1");
            if (!$default_user['ID']) {
                $default_user = $all_users[0];
            }
            if ($default_user['PASSWORD'] == '' || $default_user['PASSWORD'] == hash('sha512', '')) {
                $session->data['SITE_USERNAME'] = $default_user['USERNAME'];
                $session->data['SITE_USER_ID'] = $default_user['ID'];
                $site_username = $session->data['SITE_USERNAME'];
            } elseif ($this->action == '' && !$session->data['SITE_USERNAME']) {
                $this->redirect(ROOTHTML . 'popup/users.html');
            }
        }

        if (gr('logoff')) {
            unset($session->data['AUTHORIZED']);
            unset($session->data['USER_NAME']);
            unset($session->data['USERNAME']);
            unset($session->data['SITE_USERNAME']);
            unset($session->data['SITE_USER_ID']);
            unset($session->data["cp_requested_url"]);
            $this->redirect(ROOTHTML);
        }

        if ($site_username != '') {
            $user = SQLSelectOne("SELECT * FROM users WHERE USERNAME = '" . DBSafe($site_username) . "'");
            if ($user['ID']) {
                $out['USER_TITLE'] = $user['NAME'];
                $out['USER_AVATAR'] = $user['AVATAR'];
            }
        }

        if (isset($out['USER_TITLE'])) {
            Define('USER_TITLE', $out['USER_TITLE']);
            Define('USER_AVATAR', $out['USER_AVATAR']);
        } else {
            Define('USER_TITLE', '');
            Define('USER_AVATAR', '');
        }

        if (isset($session->data["AUTHORIZED"]) && $session->data["AUTHORIZED"]) {
            $out['AUTHORIZED_ADMIN'] = 1;
        }

        if ($this->action == '' || $this->action == 'pages') {
            $res = SQLSelect("SELECT * FROM layouts WHERE HIDDEN!=1 ORDER BY PRIORITY DESC, TITLE");
            if ($this->action != 'admin') {
                $total = count($res);
                $res2 = array();
                for ($i = 0; $i < $total; $i++) {
                    if (checkAccess('layout', $res[$i]['ID'])) {
                        $res2[] = $res[$i];
                    }
                }
                $res = $res2;
                unset($res2);
            }
            $out['LAYOUTS'] = $res;

            $total = count($out['LAYOUTS']);
            for ($i = 0; $i < $total; $i++) {
                $out['LAYOUTS'][$i]['NUM'] = $i;
            }
            $out['TOTAL_LAYOUTS'] = count($out['LAYOUTS']);
        } else {
            $out['TOTAL_LAYOUTS'] = 0;
        }

        if (isset($session->data['MY_MEMBER']) && $session->data['MY_MEMBER']) {
            $out['MY_MEMBER'] = $session->data['MY_MEMBER'];
            $tmp = SQLSelectOne("SELECT ID FROM users WHERE ID='" . (int)$out['MY_MEMBER'] . "' AND ACTIVE_CONTEXT_ID!=0 AND TIMESTAMPDIFF(SECOND, ACTIVE_CONTEXT_UPDATED, NOW())>600");
            if ($tmp['ID']) {
                SQLExec("UPDATE users SET ACTIVE_CONTEXT_ID=0, ACTIVE_CONTEXT_EXTERNAL=0 WHERE ID='" . $tmp['ID'] . "'");
            }
        }

        $out['AJAX'] = $this->ajax;
        $out['POPUP'] = $this->popup;


        $days = array(LANG_WEEK_SUN, LANG_WEEK_MON, LANG_WEEK_TUE, LANG_WEEK_WED, LANG_WEEK_THU, LANG_WEEK_FRI, LANG_WEEK_SAT);

        $out['TODAY'] = $days[date('w')] . ', ' . date('d.m.Y');
        Define('TODAY', $out['TODAY']);
        $out['REQUEST_URI'] = $_SERVER['REQUEST_URI'];

        $from_scene = gr('from_scene');
        if ($from_scene) {
            $out['FROM_SCENE'] = 1;
        }


        $ajt = gr('ajt');
        if ($ajt == '') {
            $template_file = DIR_TEMPLATES . $this->name . ".html";
        } else {
            $template_file = ROOT . 'templates_ajax/' . $this->name . "_" . $ajt . ".html";
        }

        if ($this->action == 'menu') {
            $template_file = DIR_TEMPLATES . "menu.html";
        }
        if ($this->action == 'pages') {
            $template_file = DIR_TEMPLATES . "pages.html";
        }
        if ($this->action == 'scenes') {
            $template_file = DIR_TEMPLATES . "scenes.html";
        }

        if (!$this->action && defined('SETTINGS_GENERAL_START_LAYOUT') && SETTINGS_GENERAL_START_LAYOUT != '') {

            if (SETTINGS_GENERAL_START_LAYOUT == 'homepages') {
                $this->redirect(ROOTHTML . 'pages.html');
            }
            if (SETTINGS_GENERAL_START_LAYOUT == 'menu') {
                $this->redirect(ROOTHTML . 'menu.html');
            }
            if (SETTINGS_GENERAL_START_LAYOUT == 'apps') {
                $this->redirect(ROOTHTML . 'apps.html');
            }
            if (SETTINGS_GENERAL_START_LAYOUT == 'cp') {
                $this->redirect(ROOTHTML . 'admin.php');
            }
        }


        if ($this->ajax && $this->action) {
            global $ajax;
            $ajax = 1;
            if (file_exists(DIR_MODULES . $this->action)) {
                ignore_user_abort(1);

                if (defined('SETTINGS_SITE_LANGUAGE') && file_exists(ROOT . 'languages/' . $this->action . '_' . SETTINGS_SITE_LANGUAGE . '.php'))
                    include_once(ROOT . 'languages/' . $this->action . '_' . SETTINGS_SITE_LANGUAGE . '.php');

                if (file_exists(ROOT . 'languages/' . $this->action . '_default.php'))
                    include_once(ROOT . 'languages/' . $this->action . '_default.php');

                include_once(DIR_MODULES . $this->action . '/' . $this->action . '.class.php');
                $obj = "\$object$i";
                $code = "";
                $code .= "$obj=new " . $this->action . ";\n";
                $code .= $obj . "->owner=&\$this;\n";
                $code .= $obj . "->getParams();\n";
                $code .= $obj . "->ajax=1;\n";
                $code .= $obj . "->run();\n";
                startMeasure("module_" . $this->action);
                setEvalCode($code);
                eval($code);
                setEvalCode();
                endMeasure("module_" . $this->action);

            }
            return;
        } else {
            $this->data = $out;
            $p = new parser($template_file, $this->data, $this);
            return $p->result;
        }


    }

}