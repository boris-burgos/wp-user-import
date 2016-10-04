<?php
/*
  Plugin Name: Importador Usuarios
  Description: Gestor de importaciones Viajes Oasis
  Text Domain: wp-user-import
 */
add_action('admin_menu', 'voi_register_submenu');

function voi_register_submenu() {
    add_submenu_page(
        'tools.php', 'Importador Usuarios', 'Importador Usuarios', 'manage_options', 'wp-user-import', 'voi_submenu_page_callback');
}

function voi_submenu_page_callback() {
    echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
    echo '<h2>' . __('Importador Usuarios') . '</h2>';
    echo '<div class="">';
    echo '<p>El archivo a importar debe ser formato .csv, con las columnas separadas por comas, y los campos de texto entrecomillados con comillas dobles.</p>';
    echo '<p>Los campos internos de usuarios permitidos son todos aquellos que se encuentran definidos en <a href="https://codex.wordpress.org/Function_Reference/wp_insert_user">wp_insert_user</a>.</p>';
    echo '</div>';
    voi_handle_post();
    ?>

    <form  method="post" enctype="multipart/form-data">
        <div>
            <label><?php _e('Rol para nuevos ususarios'); ?>:</label>
            <select name="rol-user">
                <?php
                $roles = get_editable_roles();
                ?>
                <?php foreach ($roles as $k => $rol) : ?>
                    <option value="<?php echo $k; ?>"><?php echo $rol['name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <input type='file' id='csv-user' name='csv_user' />
        </div>
        <?php submit_button('Importar Usuarios') ?>
    </form>

    <?php
    echo '</div>';
}

function voi_handle_post() {
    // Subimos el fichero a importar
    $res = voi_upload_file();
    if (!empty($res)) {
        if ($res['status'] == 'ok') {
            voi_import_users($res['id_media']);
        } else {
            echo '<p style="color:red;">' . $res['msg'] . '</p>';
        }
    }
}

function voi_import_users($id_media) {
    $file = get_attached_file($id_media);
    $res = array();
    if (!empty($file) && ($gestor = fopen($file, "r")) !== false) {
        $fila = 0;
        $header = array();
        while (($datos = fgetcsv($gestor)) !== false) {
            if (empty($fila)) {
                $header = $datos;
            } else {
                $res[] = voi_import_user($datos, $header);
            }
            $fila++;
        }

        echo '<div style="color:green;">' . __('Importación de usuarios realizada.') . '</div>';
        $count = 0;
        $error = '';
        foreach ($res as $r) {
            if ($r['status'] == 'error') {
                $error .= '<li>' . $r['msg'] . '</li>';
            }
            $count++;
        }

        if (!empty($error)) {
            echo '<div style="color:red;font-weight:bold;">' . __('Errores') . '</div>';
            echo '<ul style="color:red;">';
            echo $error;
            echo '</ul>';
        }

        echo '<div style="color:green;">' . $count . ' ' . __('líneas procesadas.') . '</div>';
    }
    fclose($gestor);
}

function voi_import_user($datos, $header) {
    $res = array();
    $count = count($datos);
    $user_data = array();
    $rol = $_REQUEST['rol-user'];
    $user_acf_data = array();
    for ($c = 0; $c < $count; $c++) {
        if (voi_is_internal_fields($header[$c])) {
            $user_data[$header[$c]] = $datos[$c];
        } else {
            $user_acf_data[$header[$c]] = $datos[$c];
        }
    }

    // Si no hay rol especifico para el usuario, insertamos el definido en la configuración previa
    if (!array_key_exists('role', $user_data) || empty($user_data['role'])) {
        $user_data['role'] = $rol;
    }

    // Insertamos el usuario
    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
        
        $res['status'] = 'error';
        $res['msg'] = implode("</br>", $datos) . '<br/>' . '<label style="font-weight:bold;">' . $user_id->get_error_message() . '</label>';
    } else {
        // Agregamos campos acf
        foreach ($user_acf_data as $key => $uad) {
            update_field($key, $uad, 'user_' . $user_id);
        }
        $res['status'] = 'ok';
        $res['user_id'] = $user_id;
    }

    return $res;
}

function voi_is_internal_fields($col) {
    $internal_fields = array(
        'ID',
        'user_pass',
        'user_login',
        'user_nicename',
        'user_url',
        'user_email',
        'display_name',
        'nickname',
        'first_name',
        'last_name',
        'description',
        'rich_editing',
        'user_registered',
        'role',
        'jabber',
        'alm',
        'ylm'
    );
    if (in_array($col, $internal_fields)) {
        return true;
    }
    return false;
}

function voi_upload_file() {
    $res = array();
    if (isset($_FILES['csv_user'])) {
        $pdf = $_FILES['csv_user'];

        // Se sube un media sin asociar a ningún post
        $uploaded = media_handle_upload('csv_user', 0);
        if (is_wp_error($uploaded)) {
            $res['status'] = 'error';
            $res['msg'] = __("Error subiendo fichero a importar: ") . $uploaded->get_error_message();
        } else {
            $res['status'] = 'ok';
            $res['msg'] = __("Archivo importado");
            $res['id_media'] = $uploaded;
        }
    }
    return $res;
}
