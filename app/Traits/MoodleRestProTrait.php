<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

trait MoodleRestProTrait
{
    /**
     * CORREGIDO: Crea usuario según documentación oficial de Moodle
     * Documentación: https://docs.moodle.org/dev/Web_service_API_functions#core_user_create_users
     */
    private function make_test_user($arrPost)
    {
        // Validar que el array no esté vacío y tenga las claves necesarias
        if (!is_array($arrPost)) {
            Log::error('make_test_user: arrPost no es un array: ' . gettype($arrPost));
            throw new \Exception('Datos de usuario inválidos - no es un array');
        }

        $requiredKeys = ['username', 'password', 'firstname', 'lastname', 'email'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $arrPost)) {
                Log::error("make_test_user: Clave faltante '$key' en arrPost: " . json_encode(array_keys($arrPost)));
                throw new \Exception("Clave faltante en datos de usuario: $key");
            }
        }

        // Crear usuario según formato exacto de Moodle
        $user = [
            'username' => strtolower(trim($arrPost['username'])),
            'password' => trim($arrPost['password']),
            'firstname' => trim($arrPost['firstname']),
            'lastname' => trim($arrPost['lastname']),
            'email' => strtolower(trim($arrPost['email'])),
            'auth' => isset($arrPost['auth']) ? $arrPost['auth'] : 'manual',
            'lang' => isset($arrPost['lang']) ? $arrPost['lang'] : 'es',
            'calendartype' => isset($arrPost['calendartype']) ? $arrPost['calendartype'] : 'gregorian',
        ];

        // Campos opcionales según documentación
        if (isset($arrPost['city'])) {
            $user['city'] = trim($arrPost['city']);
        }
        if (isset($arrPost['country'])) {
            $user['country'] = strtoupper(trim($arrPost['country']));
        }
        if (isset($arrPost['timezone'])) {
            $user['timezone'] = $arrPost['timezone'];
        }
        if (isset($arrPost['description'])) {
            $user['description'] = $arrPost['description'];
        }
        if (isset($arrPost['idnumber'])) {
            $user['idnumber'] = $arrPost['idnumber'];
        }
        if (isset($arrPost['institution'])) {
            $user['institution'] = $arrPost['institution'];
        }
        if (isset($arrPost['department'])) {
            $user['department'] = $arrPost['department'];
        }
        if (isset($arrPost['phone1'])) {
            $user['phone1'] = $arrPost['phone1'];
        }
        if (isset($arrPost['phone2'])) {
            $user['phone2'] = $arrPost['phone2'];
        }
        if (isset($arrPost['address'])) {
            $user['address'] = $arrPost['address'];
        }
        if (isset($arrPost['mailformat'])) {
            $user['mailformat'] = (int)$arrPost['mailformat'];
        }
        if (isset($arrPost['maildisplay'])) {
            $user['maildisplay'] = (int)$arrPost['maildisplay'];
        }
        
        Log::info('Usuario Moodle preparado: ' . json_encode($user));
        return $user;
    }

    private function make_test_course($n) 
    {
        $course = new \stdClass();
        $course->fullname = 'testcourse' . $n;
        $course->shortname = 'testcourse' . $n;
        $course->categoryid = 1;
        return $course;
    }
 
    private function create_user($user, $token)
    {
        try {
        $users = array($user);
        $params = array('users' => $users);
        $response = $this->call_moodle('core_user_create_users', $params, $token);

        if ($this->xmlresponse_is_exception($response)) {
            return array(
                'status' => 'error',
                'message' => "Caught exception: " . $response
            );
        } else {
            $user_id = $this->xmlresponse_to_id($response);
            return array(
                'status' => 'success',
                'message' => "Creado " . $user_id,
                'user_id' => $user_id // ✅ AGREGADO: Retornar el ID para facilitar uso
            );
        }
        } catch (\Exception $e) {
            Log::error('Error de Moodle: ' . $e->getMessage());
            Log::error('Error de Moodle: ' . $e->getTraceAsString());
            throw new \Exception('Error de Moodle: ' . $e->getMessage());
        }
    }

    private function get_user($user_id, $token)
    {
        $userids = array($user_id);
        $params = array('userids' => $userids);

        $response = $this->call_moodle('core_user_get_users_by_id', $params, $token);

        $user = $this->xmlresponse_to_user($response);

        if (is_array($user) && array_key_exists('id', $user))
            return $user;
        else
            return null;
    }

    private function assign_role($user_id, $role_id, $context_id, $token)
    {
        $assignment = array('roleid' => $role_id, 'userid' => $user_id, 'contextid' => $context_id);
        $assignments = array($assignment);
        $params = array('assignments' => $assignments);

        $response = $this->call_moodle('core_role_assign_roles', $params, $token);
        return $response;
    }

    private function create_course($course, $token) 
    {
        $courses = array($course);
        $params = array('courses' => $courses);

        $response = $this->call_moodle('core_course_create_courses', $params, $token);

        if ($this->xmlresponse_is_exception($response))
            throw new \Exception($response);
        else {
            $course_id = $this->xmlresponse_to_id($response);
            return $course_id;
        }
    }

    private function get_course($course_id, $token)
    {
        $courseids = array($course_id);
        $params = array('options' => array('ids' => $courseids));

        $response = $this->call_moodle('core_course_get_courses', $params, $token);

        $course = $this->xmlresponse_to_course($response);

        if (is_array($course) && array_key_exists('id', $course))
            return $course;
        else
            return null;
    }

    private function enrol($user_id, $course_id, $role_id, $token) 
    {
        $enrolment = array('roleid' => $role_id, 'userid' => $user_id, 'courseid' => $course_id);
        $enrolments = array($enrolment);
        $params = array('enrolments' => $enrolments);

        $response = $this->call_moodle('enrol_manual_enrol_users', $params, $token);
        return $response;
    }

    private function enrol_curso($user_id, $course_id, $role_id, $token) 
    {
        //funcion de finalizacion de fecha por año
        $arrFecha = localtime(time(), true);
        $iYear = (1900 + $arrFecha['tm_year']);
        $iMonth = (strlen(1 + $arrFecha['tm_mon']) > 1 ? (1 + $arrFecha['tm_mon']) : '0' . (1 + $arrFecha['tm_mon']));
        $iDay = (strlen($arrFecha['tm_mday']) > 1 ? $arrFecha['tm_mday'] : '0' . $arrFecha['tm_mday']);
        $fecha_actual = $iDay . '-' . $iMonth . '-' . $iYear;
        $timestamp = strtotime($fecha_actual."+ 365 days");//sumo 1 año

        $enrolment = array('roleid' => $role_id, 'userid' => $user_id, 'courseid' => $course_id, 'timeend' => $timestamp);
        $enrolments = array($enrolment);
        $params = array('enrolments' => $enrolments);

        $response = $this->call_moodle('enrol_manual_enrol_users', $params, $token);
        return $response;
    }

    private function get_enrolled_users($course_id, $token) 
    {
        $params = array('courseid' => $course_id);

        $response = $this->call_moodle('core_enrol_get_enrolled_users', $params, $token);

        $user = $this->xmlresponse_to_user($response);
        return $user;
    }

    private function get_user_field($params, $token)
    {
        $response = $this->call_moodle('core_user_get_users', $params, $token);

        $user = $this->xmlresponse_to_user_all($response);
        
        if (is_array($user) && array_key_exists('id', $user)) {
            return array(
                'status' => 'success',
                'message' => "Existe usuario",
                'response' => $user
            );
        } else {
            return array(
                'status' => 'error',
                'message' => "No existe usuario"
            );
        }
    }

    private function call_moodle($function_name, $params, $token)
    {
        $domain = 'https://aulavirtualprobusiness.com';

        $serverurl = $domain . '/webservice/rest/server.php'. '?wstoken=' . $token . '&wsfunction='.$function_name;

        // Usar Laravel HTTP Client en lugar de curl.php
        // Enviar como form data (application/x-www-form-urlencoded) que es lo que espera Moodle REST
        try {
            Log::info("Llamando a Moodle: {$function_name}");
            Log::info("Parámetros enviados a Moodle: " . json_encode($params));
            
            $response = Http::timeout(30)
                ->asForm()
                ->post($serverurl, $params);
                
            Log::info("Respuesta de Moodle: " . $response->body());
            return $response->body();
        } catch (\Exception $e) {
            Log::error('Error en call_moodle: ' . $e->getMessage());
            return '<EXCEPTION>' . $e->getMessage() . '</EXCEPTION>';
        }
    }

    private function xmlresponse_to_id($xml_string)
    {
        $xml_tree = new \SimpleXMLElement($xml_string);          

        $value = $xml_tree->MULTIPLE->SINGLE->KEY->VALUE;
        $id = intval(sprintf("%s", $value));

        return $id;
    }  

    private function xmlresponse_to_user($xml_string)
    {
        return $this->xmlresponse_parse_names_and_values($xml_string);
    }

    private function xmlresponse_to_course($xml_string)
    {
        return $this->xmlresponse_parse_names_and_values($xml_string);
    }

    private function xmlresponse_to_user_all($xml_string)
    {
        return $this->xmlresponse_parse_names_and_values_all($xml_string);
    }

    private function xmlresponse_parse_names_and_values($xml_string)
    {
        $xml_tree = new \SimpleXMLElement($xml_string); 

        $struct = new \StdClass();

        foreach ($xml_tree->MULTIPLE->SINGLE->KEY as $key) {
            $name = $key['name'];
            $value = (string)$key->VALUE;
            $struct->$name = $value;
        }

        return $struct;
    }

    private function xmlresponse_parse_names_and_values_all($xml_string)
    {
        $xml_tree = new \SimpleXMLElement($xml_string); 
        
        $struct = [];

        if(!empty($xml_tree->SINGLE->KEY->MULTIPLE->SINGLE->KEY)) {
            foreach ($xml_tree->SINGLE->KEY->MULTIPLE->SINGLE->KEY as $key) {
                $name = (string)$key['name'];
                $value = (string)$key->VALUE;
                $struct[$name] = $value;
            }
        }

        return $struct;
    }

    private function xmlresponse_is_exception($xml_string)
    {
        $xml_tree = new \SimpleXMLElement($xml_string);          

        $is_exception = $xml_tree->getName() == 'EXCEPTION';
        return $is_exception;
    }

    /**
     * Obtener usuario por campo específico (email, username, etc)
     */
    private function get_user_by_field($field, $value, $token)
    {
        $params = [
            'field' => $field,
            'values' => [$value]
        ];

        $response = $this->call_moodle('core_user_get_users_by_field', $params, $token);

        try {
            $xml_tree = new \SimpleXMLElement($response);
            
            // Verificar si hay resultados
            if ($xml_tree->MULTIPLE && $xml_tree->MULTIPLE->SINGLE) {
                $user = [];
                foreach ($xml_tree->MULTIPLE->SINGLE->KEY as $key) {
                    $name = (string)$key['name'];
                    $value = (string)$key->VALUE;
                    $user[$name] = $value;
                }
                
                return [
                    'status' => 'success',
                    'message' => 'Usuario encontrado',
                    'response' => $user
                ];
            }
            
            return [
                'status' => 'error',
                'message' => 'Usuario no encontrado'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al parsear respuesta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar usuario en Moodle
     */
    private function update_user($user_data, $token)
    {
        $users = [$user_data];
        $params = ['users' => $users];
        
        $response = $this->call_moodle('core_user_update_users', $params, $token);

        if ($this->xmlresponse_is_exception($response)) {
            return [
                'status' => 'error',
                'message' => "Error al actualizar: " . $response
            ];
        }
        
        return [
            'status' => 'success',
            'message' => "Usuario actualizado exitosamente"
        ];
    }  

    /**
     * ✅ CORREGIDO: Intenta crear usuario, si existe lo actualiza con nueva contraseña
     */
    public function createUser($arrPost)
    {
        try {
            $token = '2a41772b01afcf26da875fc1ab59bf45';
            
            // Validar datos antes de crear usuario
            if (empty($arrPost['username']) || empty($arrPost['password']) || 
                empty($arrPost['firstname']) || empty($arrPost['lastname']) || 
                empty($arrPost['email'])) {
                return array(
                    'status' => 'error',
                    'message' => 'Datos incompletos para crear usuario'
                );
            }
            
            // Primero intentar encontrar el usuario por email
            $existing_user = $this->get_user_by_field('email', $arrPost['email'], $token);
            
            if ($existing_user['status'] == 'success') {
                // Usuario existe, actualizarlo con nueva contraseña
                Log::info('Usuario ya existe en Moodle, actualizando contraseña: ' . $arrPost['email']);
                
                $user_id = $existing_user['response']['id'];
                $update_data = [
                    'id' => (int)$user_id,
                    'password' => $arrPost['password']
                ];
                
                $update_result = $this->update_user($update_data, $token);
                
                if ($update_result['status'] == 'success') {
                    return [
                        'status' => 'success',
                        'message' => 'Usuario existente actualizado',
                        'user_id' => $user_id
                    ];
                }
                
                return $update_result;
            }
            
            // Usuario no existe, crearlo
            $user_data_1 = $this->make_test_user($arrPost);
            
            // Log para debug
            Log::info('Creando nuevo usuario Moodle: ' . json_encode($user_data_1));
            
            $user_id_1 = $this->create_user($user_data_1, $token);
            return $user_id_1;
        } 
        catch (\Exception $e) {
            Log::error('Error de Moodle: ' . $e->getMessage());
            Log::error('Error de Moodle: ' . $e->getTraceAsString());
            return array(
                'status' => 'error',
                'message' => "Error de Moodle: " . $e->getMessage()
            );
        }
    }

    public function getUser($arrParams)
    {
        try {
            $token = '2a41772b01afcf26da875fc1ab59bf45';
            $user_id_1 = $this->get_user_field($arrParams, $token);
            return $user_id_1;
        } 
        catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => "Caught exception: " . $e->getMessage()
            );
        }
    }

    /**
     * ✅ MEJORADO: Mejor manejo de errores en inscripción a cursos
     */
    public function crearCursoUsuario($arrParams)
    {
        try {
            $token = '2a41772b01afcf26da875fc1ab59bf45';

            $user_id_1 = $arrParams['id_usuario'];
            $role_id = 5;//usuario invitado

            // Array de cursos para inscribir
            $courses = [
                4, // Módulo 1: Introducción a las importaciones
                5, // Módulo 2: Importación Simplificada
                6, // Módulo 3: Importación de USA
                7, // Módulo 4: Importación Definitiva
                10, // Módulo 5: Carga Consolidada
                9  // Resumen de Módulos
            ];

            $enrolled_courses = [];
            $errors = [];

            foreach ($courses as $course_id) {
                $response_xml = $this->enrol_curso($user_id_1, $course_id, $role_id, $token);
                
                try {
                    $response_obj = new \SimpleXMLElement($response_xml);
                    
                    if (isset($response_obj->MESSAGE)) {
                        $errors[] = "Curso $course_id: " . (string)$response_obj->MESSAGE;
                    } else {
                        $enrolled_courses[] = $course_id;
                    }
                } catch (\Exception $xml_error) {
                    $errors[] = "Curso $course_id: Error parsing XML - " . $xml_error->getMessage();
                }
            }

            if (count($errors) > 0) {
                return array(
                    'status' => 'error',
                    'message' => 'Errores en inscripción: ' . implode('; ', $errors),
                    'enrolled_courses' => $enrolled_courses
                );
            } else {
                return array(
                    'status' => 'success',
                    'message' => "Usuario inscrito correctamente en todos los cursos",
                    'enrolled_courses' => $enrolled_courses
                );
            }
        } 
        catch (\Exception $e) {
            return array(
                'status' => 'error',
                'message' => "Caught exception: " . $e->getMessage()
            );
        }
    }
}
